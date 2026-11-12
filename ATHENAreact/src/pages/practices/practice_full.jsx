import "./practice_full.css";
import { useEffect, useState, useMemo, useCallback } from "react";
import { useSearchParams, useNavigate, Link } from "react-router-dom";
import { useShallow } from "zustand/react/shallow";
import Zorrito from "../../assets/images/Zorrito2D.png";
import useExamStore from "../../stores/useExamStore";
import PracticeHeader from "./components/PracticeHeader";
import PracticeOptions from "./components/PracticeOptions";
import PracticeFooter from "./components/PracticeFooter";
import PracticeLightbox from "./components/PracticeLightbox";
import {
    getStudentQuestions,
    submitStudentAnswer,
    getStudentMediaUrl,
} from "../../api/studentQuestions";

const PracticeFull = () => {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();

    // Parámetros de consulta
    const subjectId = searchParams.get("subject_id") || "1";
    const subjectName = searchParams.get("subject_name") || "Lectura Crítica";
    const limitParam = parseInt(searchParams.get("limit") || "49", 10);
    const difficultyParam = searchParams.get("difficulty") || "all";

    const [questions, setQuestions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [isSubmittingAnswer, setIsSubmittingAnswer] = useState(false);
    const [submitError, setSubmitError] = useState("");

    // Lightbox para ampliación de imágenes
    const [lightboxState, setLightboxState] = useState({
        isOpen: false,
        src: "",
        alt: "",
        caption: "",
    });

    // Modal de resultados al finalizar práctica
    const [isSummaryOpen, setIsSummaryOpen] = useState(false);

    // Control de colapso de estímulo y transcripción multimodal
    const [stimulusCollapsed, setStimulusCollapsed] = useState(false);
    const [showVisualTranscription, setShowVisualTranscription] = useState(false);

    // Temporizador de sesión
    const [secondsElapsed, setSecondsElapsed] = useState(0);

    // Store global de Zustand
    const currentIndex = useExamStore((state) => state.currentIndex);
    const answers = useExamStore((state) => state.answers);
    const feedbacks = useExamStore((state) => state.feedbacks);
    const { initPractice, resetPractice, setFeedback, setCurrentIndex } = useExamStore(
        useShallow((state) => ({
            initPractice: state.initPractice,
            resetPractice: state.resetPractice,
            setFeedback: state.setFeedback,
            setCurrentIndex: state.setCurrentIndex,
        }))
    );

    /* ============================================================
     * CARGAR PREGUNTAS REALES DESDE EL QUESTION BANK
     * ============================================================ */
    useEffect(() => {
        const fetchQuestions = async () => {
            try {
                setLoading(true);
                setError("");
                resetPractice();

                const params = {
                    subject_id: subjectId,
                    per_page: limitParam || 49,
                };
                if (difficultyParam && difficultyParam !== "all") {
                    params.difficulty = difficultyParam;
                }

                const res = await getStudentQuestions(params);

                if (res && res.success && Array.isArray(res.data) && res.data.length > 0) {
                    setQuestions(res.data);

                    // Inicializar respuestas previas si el estudiante ya había resuelto alguna
                    const preselected = {};
                    const prefeedbacks = {};
                    res.data.forEach((q) => {
                        if (q.student_progress?.answered) {
                            if (q.student_progress.selected_option_id) {
                                preselected[q.id] = q.student_progress.selected_option_id;
                            }
                            prefeedbacks[q.id] = {
                                is_correct: q.student_progress.is_correct,
                                selected_option_letter: q.student_progress.selected_option_letter,
                                correct_option_letter: q.student_progress.is_correct
                                    ? q.student_progress.selected_option_letter
                                    : null,
                            };
                        }
                    });

                    initPractice(preselected, 0, prefeedbacks);
                } else {
                    throw new Error(
                        `No hay preguntas publicadas disponibles para ${subjectName} en este momento.`
                    );
                }
            } catch (err) {
                console.error("Error al cargar preguntas para práctica:", err);
                setError(
                    err?.response?.data?.message ||
                        err?.message ||
                        "No se pudieron cargar las preguntas del banco de preguntas oficial."
                );
            } finally {
                setLoading(false);
            }
        };

        fetchQuestions();
    }, [subjectId, subjectName, limitParam, difficultyParam, initPractice, resetPractice]);

    /* ============================================================
     * TEMPORIZADOR
     * ============================================================ */
    useEffect(() => {
        if (loading || questions.length === 0 || isSummaryOpen) return;

        const timer = setInterval(() => {
            setSecondsElapsed((prev) => prev + 1);
        }, 1000);

        return () => clearInterval(timer);
    }, [loading, questions.length, isSummaryOpen]);

    const formatTimer = (totalSeconds) => {
        const mins = Math.floor(totalSeconds / 60);
        const secs = totalSeconds % 60;
        return `${String(mins).padStart(2, "0")}:${String(secs).padStart(2, "0")}`;
    };

    /* ============================================================
     * PREGUNTA Y ESTÍMULO ACTUAL
     * ============================================================ */
    const currentQuestion = questions[currentIndex] || null;
    const prevQuestion = currentIndex > 0 ? questions[currentIndex - 1] : null;

    const isSameStimulusAsPrevious = Boolean(
        currentQuestion?.stimulus_id &&
        prevQuestion?.stimulus_id &&
        currentQuestion.stimulus_id === prevQuestion.stimulus_id
    );

    // Auto colapsar estímulo solo si es continuación del mismo bloque
    const [lastIndex, setLastIndex] = useState(currentIndex);
    if (lastIndex !== currentIndex) {
        setLastIndex(currentIndex);
        setStimulusCollapsed(Boolean(isSameStimulusAsPrevious));
        setShowVisualTranscription(false);
        setSubmitError("");
    }

    const currentStimulus = currentQuestion?.stimulus || null;
    const currentOptions = currentQuestion?.options || [];
    const currentFeedback = currentQuestion ? feedbacks[currentQuestion.id] : null;
    const isAnswerConfirmed = Boolean(currentFeedback);
    const selectedOptionId = currentQuestion ? answers[currentQuestion.id] : null;

    /* ============================================================
     * CONFIRMAR RESPUESTA EN BACKEND
     * ============================================================ */
    const handleConfirmAnswer = async () => {
        if (!currentQuestion || !selectedOptionId || isAnswerConfirmed || isSubmittingAnswer) {
            return;
        }

        try {
            setIsSubmittingAnswer(true);
            setSubmitError("");

            const res = await submitStudentAnswer(currentQuestion.id, {
                selected_option_id: selectedOptionId,
                practice_mode: "practice",
                time_spent_seconds: secondsElapsed,
            });

            if (res && res.success && res.data) {
                setFeedback(currentQuestion.id, res.data);
            } else {
                throw new Error(res?.message || "Error al calificar respuesta.");
            }
        } catch (err) {
            console.error("Error al enviar respuesta:", err);
            setSubmitError(
                err?.response?.data?.message ||
                    err?.message ||
                    "No se pudo registrar tu respuesta en el servidor."
            );
        } finally {
            setIsSubmittingAnswer(false);
        }
    };

    /* ============================================================
     * RECURSOS VISUALES DEL ESTÍMULO Y PREGUNTA
     * ============================================================ */
    const confirmedStimulusMedia = useMemo(() => {
        if (!currentStimulus) return [];
        return (
            currentStimulus.confirmed_media_assets ||
            currentStimulus.confirmedMediaAssets ||
            []
        );
    }, [currentStimulus]);

    const stimulusMediaPath = currentStimulus?.media_path || (confirmedStimulusMedia[0]?.storage_path ?? null);

    const questionMediaPath =
        currentQuestion?.question_image_path ||
        currentQuestion?.confirmed_media_assets?.[0]?.storage_path ||
        null;

    const openLightbox = (src, alt, caption) => {
        setLightboxState({
            isOpen: true,
            src,
            alt: alt || "Recurso visual",
            caption: caption || "",
        });
    };

    /* ============================================================
     * ESTADÍSTICAS DEL INTENTO ACTUAL
     * ============================================================ */
    const stats = useMemo(() => {
        const total = questions.length;
        const answeredIds = Object.keys(feedbacks);
        const answeredCount = answeredIds.length;
        let correctCount = 0;
        answeredIds.forEach((id) => {
            if (feedbacks[id]?.is_correct) correctCount++;
        });
        const incorrectCount = answeredCount - correctCount;
        const accuracy = answeredCount > 0 ? Math.round((correctCount / answeredCount) * 100) : 0;
        return { total, answeredCount, correctCount, incorrectCount, accuracy };
    }, [questions, feedbacks]);

    /* ============================================================
     * ESTADO DE CARGA Y ERROR
     * ============================================================ */
    if (loading) {
        return (
            <div className="main__content">
                <section className="question" style={{ textAlign: "center", padding: "60px 20px" }}>
                    <div style={{ fontSize: "36px", color: "#38bdf8", marginBottom: "18px" }}>
                        <i className="fas fa-spinner fa-spin"></i>
                    </div>
                    <h1 className="question__title" style={{ fontSize: "22px" }}>
                        Cargando preguntas oficiales de {subjectName}...
                    </h1>
                    <p style={{ color: "#94a3b8" }}>
                        Recuperando reactivos reales y recursos visuales desde el banco institucional.
                    </p>
                </section>
            </div>
        );
    }

    if (error && questions.length === 0) {
        return (
            <div className="main__content">
                <section className="question" style={{ textAlign: "center", padding: "60px 20px" }}>
                    <div style={{ fontSize: "36px", color: "#ef4444", marginBottom: "18px" }}>
                        <i className="fas fa-triangle-exclamation"></i>
                    </div>
                    <h1 className="question__title" style={{ fontSize: "22px" }}>
                        No se pudo iniciar la práctica
                    </h1>
                    <p style={{ color: "#94a3b8", marginBottom: "24px" }}>{error}</p>
                    <Link to="/practices" onClick={() => resetPractice()}>
                        <button type="button" className="footer-btn footer-btn--primary">
                            Volver a Selección de Áreas
                        </button>
                    </Link>
                </section>
            </div>
        );
    }

    return (
        <>
            <div className="main__content">
                {/* CABECERA Y CRONÓMETRO */}
                <PracticeHeader
                    subjectName={currentQuestion?.subject?.name || subjectName}
                    topicName={currentQuestion?.topic?.name || ""}
                    totalQuestions={questions.length}
                    secondsElapsed={secondsElapsed}
                    formatTimer={formatTimer}
                />

                {/* BARRA DE NAVEGACIÓN RÁPIDA ENTRE PREGUNTAS (STRIP) */}
                <div
                    style={{
                        display: "flex",
                        alignItems: "center",
                        gap: "6px",
                        overflowX: "auto",
                        padding: "8px 4px",
                        marginBottom: "16px",
                        scrollbarWidth: "thin",
                    }}
                >
                    {questions.map((q, idx) => {
                        const isCurrent = idx === currentIndex;
                        const fb = feedbacks[q.id];
                        let btnBg = "rgba(15, 23, 42, 0.7)";
                        let btnBorder = "rgba(148, 163, 184, 0.25)";
                        let btnColor = "#94a3b8";

                        if (fb) {
                            if (fb.is_correct) {
                                btnBg = "rgba(34, 197, 94, 0.2)";
                                btnBorder = "#22c55e";
                                btnColor = "#4ade80";
                            } else {
                                btnBg = "rgba(239, 68, 68, 0.2)";
                                btnBorder = "#ef4444";
                                btnColor = "#f87171";
                            }
                        }

                        if (isCurrent) {
                            btnBorder = "#38bdf8";
                            btnBg = "rgba(56, 189, 248, 0.2)";
                            btnColor = "#ffffff";
                        }

                        return (
                            <button
                                key={q.id}
                                type="button"
                                onClick={() => {
                                    setCurrentIndex(idx);
                                    window.scrollTo({ top: 0, behavior: "smooth" });
                                }}
                                style={{
                                    minWidth: "34px",
                                    height: "32px",
                                    borderRadius: "6px",
                                    border: `1px solid ${btnBorder}`,
                                    backgroundColor: btnBg,
                                    color: btnColor,
                                    fontSize: "12px",
                                    fontWeight: isCurrent ? 700 : 500,
                                    cursor: "pointer",
                                    display: "inline-flex",
                                    alignItems: "center",
                                    justifyContent: "center",
                                    flexShrink: 0,
                                    transition: "all 0.15s ease",
                                }}
                                title={`Pregunta #${q.order_in_group || idx + 1}${fb ? (fb.is_correct ? " (Correcta)" : " (Incorrecta)") : " (Sin responder)"}`}
                            >
                                {q.order_in_group || idx + 1}
                            </button>
                        );
                    })}
                </div>

                {/* ============================================================
                 * PANEL DE ESTÍMULO (TEXTO / CARICATURA / HISTORIETA / INFOGRAFÍA)
                 * ============================================================ */}
                {currentStimulus && (
                    <section className="stimulus-panel">
                        <div
                            className="stimulus-header"
                            onClick={() => setStimulusCollapsed(!stimulusCollapsed)}
                        >
                            <span className="stimulus-badge">
                                <i className="fas fa-book-open"></i> Texto de Lectura
                            </span>
                            <span className="stimulus-title">
                                {currentStimulus.title || "Lectura compartida"}
                                {currentStimulus.source_page && (
                                    <small style={{ color: "#38bdf8", marginLeft: "8px", fontWeight: 700 }}>
                                        · Pág. {currentStimulus.source_page}
                                    </small>
                                )}
                                {isSameStimulusAsPrevious && (
                                    <small style={{ color: "#94a3b8", marginLeft: "8px" }}>
                                        (Mismo texto que la pregunta anterior)
                                    </small>
                                )}
                            </span>
                            <button
                                type="button"
                                className="stimulus-toggle-btn"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    setStimulusCollapsed(!stimulusCollapsed);
                                }}
                            >
                                {stimulusCollapsed ? "Ver lectura completa" : "Ocultar lectura"}
                                <i className={`fas ${stimulusCollapsed ? "fa-chevron-down" : "fa-chevron-up"}`}></i>
                            </button>
                        </div>

                        {!stimulusCollapsed && (
                            <div className="stimulus-body">
                                {currentStimulus.instructions && (
                                    <div className="stimulus-instructions">
                                        <i className="fas fa-circle-info"></i>
                                        <span>{currentStimulus.instructions}</span>
                                    </div>
                                )}

                                {/* RECURSO VISUAL CONFIRMADO DEL ESTÍMULO */}
                                {stimulusMediaPath && (
                                    <div className="stimulus-media" style={{ margin: "16px 0", textAlign: "center" }}>
                                        <div
                                            style={{
                                                display: "inline-block",
                                                position: "relative",
                                                cursor: "zoom-in",
                                                borderRadius: "10px",
                                                overflow: "hidden",
                                                background: "#ffffff",
                                                padding: "6px",
                                                border: "1px solid rgba(148, 163, 184, 0.3)",
                                            }}
                                            onClick={() =>
                                                openLightbox(
                                                    getStudentMediaUrl(stimulusMediaPath),
                                                    currentStimulus.title,
                                                    `Recurso visual de la página ${currentStimulus.source_page || "cuadernillo"}`
                                                )
                                            }
                                            title="Haz clic para ampliar la imagen"
                                        >
                                            <img
                                                src={getStudentMediaUrl(stimulusMediaPath)}
                                                alt={currentStimulus.title || "Recurso gráfico del estímulo"}
                                                style={{
                                                    maxWidth: "100%",
                                                    maxHeight: "380px",
                                                    objectFit: "contain",
                                                    display: "block",
                                                }}
                                            />
                                            <span
                                                style={{
                                                    position: "absolute",
                                                    bottom: "10px",
                                                    right: "10px",
                                                    background: "rgba(15, 23, 42, 0.85)",
                                                    color: "#38bdf8",
                                                    padding: "4px 8px",
                                                    borderRadius: "6px",
                                                    fontSize: "11px",
                                                    fontWeight: 600,
                                                    display: "inline-flex",
                                                    alignItems: "center",
                                                    gap: "4px",
                                                }}
                                            >
                                                <i className="fas fa-magnifying-glass-plus"></i> Ampliar
                                            </span>
                                        </div>
                                    </div>
                                )}

                                {/* TEXTO ESCRITO DEL ESTÍMULO */}
                                {currentStimulus.content_text && (
                                    <div className="stimulus-text" style={{ fontSize: "15px", lineHeight: "1.7", color: "#cbd5e1" }}>
                                        {currentStimulus.content_text}
                                    </div>
                                )}

                                {/* BOTÓN DE ANÁLISIS MULTIMODAL COMPLEMENTARIO */}
                                {(currentStimulus.visual_transcription || currentStimulus.visual_description) && (
                                    <div style={{ marginTop: "14px" }}>
                                        <button
                                            type="button"
                                            onClick={() => setShowVisualTranscription(!showVisualTranscription)}
                                            style={{
                                                background: "rgba(56, 189, 248, 0.1)",
                                                border: "1px solid rgba(56, 189, 248, 0.3)",
                                                color: "#38bdf8",
                                                borderRadius: "6px",
                                                padding: "6px 12px",
                                                fontSize: "12px",
                                                fontWeight: 600,
                                                cursor: "pointer",
                                                display: "inline-flex",
                                                alignItems: "center",
                                                gap: "6px",
                                            }}
                                        >
                                            <i className="fas fa-wand-magic-sparkles"></i>
                                            {showVisualTranscription ? "Ocultar Transcripción Textual" : "Ver Transcripción de Viñetas / Gráficas"}
                                        </button>

                                        {showVisualTranscription && (
                                            <div
                                                style={{
                                                    marginTop: "10px",
                                                    padding: "12px 14px",
                                                    borderRadius: "8px",
                                                    background: "rgba(15, 23, 42, 0.85)",
                                                    border: "1px solid rgba(56, 189, 248, 0.25)",
                                                    fontSize: "13px",
                                                    lineHeight: "1.6",
                                                    color: "#e2e8f0",
                                                }}
                                            >
                                                {currentStimulus.visual_transcription && (
                                                    <div style={{ marginBottom: "8px" }}>
                                                        <strong style={{ color: "#7dd3fc" }}>Transcripción de viñetas/datos: </strong>
                                                        {currentStimulus.visual_transcription}
                                                    </div>
                                                )}
                                                {currentStimulus.visual_description && (
                                                    <div>
                                                        <strong style={{ color: "#a5b4fc" }}>Descripción visual: </strong>
                                                        {currentStimulus.visual_description}
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>
                        )}
                    </section>
                )}

                {/* ============================================================
                 * CONTENEDOR DE LA PREGUNTA ACTIVA
                 * ============================================================ */}
                <section className="question">
                    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "14px", flexWrap: "wrap", gap: "8px" }}>
                        <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                            <span
                                style={{
                                    fontSize: "12px",
                                    fontWeight: 700,
                                    color: "#38bdf8",
                                    background: "rgba(56, 189, 248, 0.15)",
                                    border: "1px solid rgba(56, 189, 248, 0.3)",
                                    padding: "3px 10px",
                                    borderRadius: "6px",
                                }}
                            >
                                Reactivo #{currentQuestion?.order_in_group || currentIndex + 1}
                            </span>
                            {currentQuestion?.source_page && (
                                <span style={{ fontSize: "11px", color: "#94a3b8" }}>
                                    <i className="fas fa-file-lines" style={{ marginRight: "4px" }}></i>
                                    Pág. {currentQuestion.source_page}
                                </span>
                            )}
                        </div>

                        <span style={{ fontSize: "11px", color: "#94a3b8", background: "rgba(255,255,255,0.05)", padding: "4px 8px", borderRadius: "6px" }}>
                            Sesión Oficial · {questions.length} preguntas
                        </span>
                    </div>

                    {/* ENUNCIADO DE LA PREGUNTA */}
                    <h1 className="question__title" style={{ fontSize: "19px", lineHeight: "1.5", color: "#f8fafc" }}>
                        {currentQuestion?.statement}
                    </h1>

                    {/* IMAGEN ESPECÍFICA DE LA PREGUNTA */}
                    {questionMediaPath && (
                        <div className="question-image-box" style={{ margin: "16px 0", textAlign: "center" }}>
                            <div
                                style={{
                                    display: "inline-block",
                                    cursor: "zoom-in",
                                    borderRadius: "8px",
                                    overflow: "hidden",
                                    background: "#ffffff",
                                    padding: "6px",
                                }}
                                onClick={() =>
                                    openLightbox(
                                        getStudentMediaUrl(questionMediaPath),
                                        `Figura de la Pregunta #${currentQuestion.order_in_group}`
                                    )
                                }
                            >
                                <img
                                    src={getStudentMediaUrl(questionMediaPath)}
                                    alt="Figura o esquema de la pregunta"
                                    style={{ maxWidth: "100%", maxHeight: "300px", objectFit: "contain" }}
                                />
                            </div>
                        </div>
                    )}

                    {/* OPCIONES DE RESPUESTA A, B, C, D */}
                    <PracticeOptions
                        question={currentQuestion}
                        options={currentOptions}
                        disabled={isSubmittingAnswer || isAnswerConfirmed}
                    />

                    {/* MENSAJE DE ERROR AL CONFIRMAR */}
                    {submitError && (
                        <div style={{ marginTop: "12px", background: "rgba(239, 68, 68, 0.15)", border: "1px solid #ef4444", color: "#fca5a5", padding: "10px 14px", borderRadius: "8px", fontSize: "13px" }}>
                            <i className="fas fa-triangle-exclamation" style={{ marginRight: "6px" }}></i>
                            {submitError}
                        </div>
                    )}

                    {/* BOTÓN CONFIRMAR RESPUESTA (SI NO HA SIDO CONFIRMADA) */}
                    {!isAnswerConfirmed && (
                        <div style={{ marginTop: "20px", display: "flex", justifyContent: "flex-end" }}>
                            <button
                                type="button"
                                onClick={handleConfirmAnswer}
                                disabled={!selectedOptionId || isSubmittingAnswer}
                                style={{
                                    background: selectedOptionId ? "linear-gradient(135deg, #0284c7 0%, #38bdf8 100%)" : "rgba(148, 163, 184, 0.2)",
                                    color: selectedOptionId ? "#ffffff" : "#64748b",
                                    border: selectedOptionId ? "1px solid #38bdf8" : "1px solid rgba(148, 163, 184, 0.25)",
                                    borderRadius: "8px",
                                    padding: "10px 22px",
                                    fontSize: "14px",
                                    fontWeight: 700,
                                    cursor: selectedOptionId && !isSubmittingAnswer ? "pointer" : "not-allowed",
                                    boxShadow: selectedOptionId ? "0 4px 14px rgba(56, 189, 248, 0.4)" : "none",
                                    transition: "all 0.2s ease",
                                    display: "inline-flex",
                                    alignItems: "center",
                                    gap: "8px",
                                }}
                            >
                                {isSubmittingAnswer ? (
                                    <>
                                        <i className="fas fa-spinner fa-spin"></i> Calificando...
                                    </>
                                ) : (
                                    <>
                                        <i className="fas fa-circle-check"></i> Confirmar Respuesta
                                    </>
                                )}
                            </button>
                        </div>
                    )}

                    {/* ============================================================
                     * RETROALIMENTACIÓN INMEDIATA TRAS CONFIRMAR
                     * ============================================================ */}
                    {isAnswerConfirmed && (
                        <div
                            style={{
                                marginTop: "20px",
                                borderRadius: "10px",
                                border: currentFeedback.is_correct ? "1px solid #22c55e" : "1px solid #ef4444",
                                background: currentFeedback.is_correct ? "rgba(34, 197, 94, 0.08)" : "rgba(239, 68, 68, 0.08)",
                                padding: "16px 18px",
                                animation: "fadeIn 0.3s ease",
                            }}
                        >
                            <div style={{ display: "flex", alignItems: "center", gap: "10px", marginBottom: "8px" }}>
                                <i
                                    className={`fas ${currentFeedback.is_correct ? "fa-circle-check" : "fa-circle-xmark"}`}
                                    style={{
                                        fontSize: "20px",
                                        color: currentFeedback.is_correct ? "#22c55e" : "#ef4444",
                                    }}
                                ></i>
                                <span
                                    style={{
                                        fontSize: "16px",
                                        fontWeight: 700,
                                        color: currentFeedback.is_correct ? "#4ade80" : "#f87171",
                                    }}
                                >
                                    {currentFeedback.is_correct ? "¡Excelente! Respuesta Correcta" : "Respuesta Incorrecta"}
                                </span>
                            </div>

                            {!currentFeedback.is_correct && currentFeedback.correct_option_letter && (
                                <p style={{ color: "#f1f5f9", fontSize: "14px", marginBottom: "8px" }}>
                                    La clave oficial verificada del ICFES para esta pregunta es la opción{" "}
                                    <strong style={{ color: "#4ade80", fontSize: "15px" }}>
                                        {currentFeedback.correct_option_letter}
                                    </strong>.
                                </p>
                            )}

                            {currentFeedback.explanation && (
                                <div style={{ marginTop: "10px", paddingTop: "10px", borderTop: "1px dashed rgba(148, 163, 184, 0.2)" }}>
                                    <strong style={{ color: "#94a3b8", fontSize: "12px", textTransform: "uppercase", letterSpacing: "0.5px", display: "block", marginBottom: "4px" }}>
                                        Orientación Pedagógica
                                    </strong>
                                    <p style={{ color: "#cbd5e1", fontSize: "13px", lineHeight: "1.6", margin: 0 }}>
                                        {currentFeedback.explanation}
                                    </p>
                                </div>
                            )}
                        </div>
                    )}
                </section>

                {/* PIE DE PÁGINA / NAVEGACIÓN ANTERIOR - SIGUIENTE - FINALIZAR */}
                <PracticeFooter
                    totalQuestions={questions.length}
                    saving={isSubmittingAnswer}
                    finishing={false}
                    onFinish={() => setIsSummaryOpen(true)}
                />
            </div>

            {/* LIGHTBOX DE IMÁGENES AMPLIADAS */}
            <PracticeLightbox
                isOpen={lightboxState.isOpen}
                onClose={() => setLightboxState((prev) => ({ ...prev, isOpen: false }))}
                src={lightboxState.src}
                alt={lightboxState.alt}
                caption={lightboxState.caption}
            />

            {/* MODAL DE RESULTADOS / RESUMEN DE LA PRÁCTICA */}
            {isSummaryOpen && (
                <div
                    className="practice-modal-overlay"
                    onClick={() => setIsSummaryOpen(false)}
                    style={{ position: "fixed", top: 0, left: 0, right: 0, bottom: 0, background: "rgba(10, 15, 30, 0.85)", zIndex: 9000, display: "flex", alignItems: "center", justifyContent: "center", padding: "20px" }}
                >
                    <div
                        onClick={(e) => e.stopPropagation()}
                        style={{
                            background: "#0f172a",
                            border: "1px solid rgba(56, 189, 248, 0.3)",
                            borderRadius: "14px",
                            padding: "24px",
                            maxWidth: "480px",
                            width: "100%",
                            boxShadow: "0 25px 60px rgba(0, 0, 0, 0.7)",
                        }}
                    >
                        <div style={{ textAlign: "center", marginBottom: "20px" }}>
                            <div
                                style={{
                                    width: "60px",
                                    height: "60px",
                                    borderRadius: "50%",
                                    background: "rgba(56, 189, 248, 0.15)",
                                    border: "2px solid #38bdf8",
                                    color: "#38bdf8",
                                    fontSize: "24px",
                                    display: "inline-flex",
                                    alignItems: "center",
                                    justifyContent: "center",
                                    marginBottom: "12px",
                                }}
                            >
                                <i className="fas fa-trophy"></i>
                            </div>
                            <h2 style={{ color: "#f8fafc", fontSize: "20px", fontWeight: 700, margin: 0 }}>
                                Resumen de Entrenamiento
                            </h2>
                            <p style={{ color: "#94a3b8", fontSize: "13px", marginTop: "4px" }}>
                                Práctica en {subjectName} · {formatTimer(secondsElapsed)} transcurridos
                            </p>
                        </div>

                        {/* ESTADÍSTICAS EN GRID */}
                        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "12px", marginBottom: "20px" }}>
                            <div style={{ background: "rgba(255, 255, 255, 0.03)", padding: "12px", borderRadius: "8px", border: "1px solid rgba(148, 163, 184, 0.15)", textAlign: "center" }}>
                                <span style={{ color: "#94a3b8", fontSize: "11px", display: "block" }}>Respondidas</span>
                                <strong style={{ color: "#f8fafc", fontSize: "18px" }}>
                                    {stats.answeredCount} / {stats.total}
                                </strong>
                            </div>

                            <div style={{ background: "rgba(34, 197, 94, 0.1)", padding: "12px", borderRadius: "8px", border: "1px solid rgba(34, 197, 94, 0.3)", textAlign: "center" }}>
                                <span style={{ color: "#4ade80", fontSize: "11px", display: "block" }}>Aciertos</span>
                                <strong style={{ color: "#4ade80", fontSize: "18px" }}>
                                    {stats.correctCount}
                                </strong>
                            </div>

                            <div style={{ background: "rgba(239, 68, 68, 0.1)", padding: "12px", borderRadius: "8px", border: "1px solid rgba(239, 68, 68, 0.3)", textAlign: "center" }}>
                                <span style={{ color: "#f87171", fontSize: "11px", display: "block" }}>Incorrectas</span>
                                <strong style={{ color: "#f87171", fontSize: "18px" }}>
                                    {stats.incorrectCount}
                                </strong>
                            </div>

                            <div style={{ background: "rgba(56, 189, 248, 0.1)", padding: "12px", borderRadius: "8px", border: "1px solid rgba(56, 189, 248, 0.3)", textAlign: "center" }}>
                                <span style={{ color: "#38bdf8", fontSize: "11px", display: "block" }}>Efectividad</span>
                                <strong style={{ color: "#38bdf8", fontSize: "18px" }}>
                                    {stats.accuracy}%
                                </strong>
                            </div>
                        </div>

                        {/* ACCIONES */}
                        <div style={{ display: "flex", gap: "10px", justifyContent: "flex-end" }}>
                            <button
                                type="button"
                                onClick={() => setIsSummaryOpen(false)}
                                style={{
                                    padding: "8px 16px",
                                    borderRadius: "8px",
                                    background: "transparent",
                                    border: "1px solid rgba(148, 163, 184, 0.3)",
                                    color: "#cbd5e1",
                                    cursor: "pointer",
                                    fontSize: "13px",
                                }}
                            >
                                Seguir practicando
                            </button>

                            <button
                                type="button"
                                onClick={() => {
                                    resetPractice();
                                    navigate("/practices");
                                }}
                                style={{
                                    padding: "8px 18px",
                                    borderRadius: "8px",
                                    background: "linear-gradient(135deg, #0284c7 0%, #38bdf8 100%)",
                                    border: "none",
                                    color: "#ffffff",
                                    fontWeight: 700,
                                    cursor: "pointer",
                                    fontSize: "13px",
                                }}
                            >
                                Salir a Selección de Áreas
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* ASISTENTE ZORRITO */}
            <div className="assistant">
                <div className="assistant__message">
                    ¡Excelente trabajo! Entrenar con preguntas reales del cuadernillo afianza tus competencias Saber 11.
                </div>
                <div className="assistant__bot">
                    <img src={Zorrito} alt="Zorrito" />
                </div>
            </div>
        </>
    );
};

export default PracticeFull;
