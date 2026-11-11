import "./mock_exam.css";
import { useEffect, useState, useMemo, useRef, useCallback } from "react";
import { useSearchParams, useNavigate } from "react-router-dom";

import Zorrito from "../../assets/images/Zorrito2D.png";
import PracticeLightbox from "../practices/components/PracticeLightbox";
import {
    getExamAttempt,
    getActiveExam,
    saveExamAnswer,
    finishExamAttempt,
} from "../../api/exams";
import { getStudentMediaUrl } from "../../api/studentQuestions";

const MockExam = () => {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();

    // ID del intento pasado por URL (o recuperado de backend)
    const urlExamId = searchParams.get("id");
    const [examId, setExamId] = useState(urlExamId || "");

    const [attemptData, setAttemptData] = useState(null);
    const [questions, setQuestions] = useState([]);
    const [currentQuestionIndex, setCurrentQuestionIndex] = useState(0);

    // Mapa de respuestas: { [questionId]: { selected_option_id, selected_option_letter } }
    const [answers, setAnswers] = useState({});
    // Set de preguntas marcadas para revisar: Set<questionId>
    const [flaggedQuestions, setFlaggedQuestions] = useState(new Set());
    // Registro de tiempo invertido por pregunta en segundos
    const [timePerQuestion, setTimePerQuestion] = useState({});

    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [finishing, setFinishing] = useState(false);
    const [error, setError] = useState("");

    // Temporizador en segundos (sincronizado con remaining_seconds del backend)
    const [timeLeft, setTimeLeft] = useState(null);

    // Lightbox modal para zoom de infografías y caricaturas
    const [lightboxState, setLightboxState] = useState({
        isOpen: false,
        src: "",
        alt: "",
        caption: "",
    });

    // Control de colapso de lectura (estímulo)
    const [stimulusCollapsed, setStimulusCollapsed] = useState(false);

    // Modal de confirmación para finalizar simulacro
    const [isFinishModalOpen, setIsFinishModalOpen] = useState(false);

    // Referencia al temporizador de segundos por pregunta
    const questionStartTimeRef = useRef(Date.now());

    /* ============================================================
     * CARGAR INTENTO O RECUPERAR SIMULACRO ACTIVO
     * ============================================================ */
    useEffect(() => {
        let isMounted = true;

        const loadExam = async () => {
            try {
                setLoading(true);
                setError("");

                let activeId = urlExamId;

                // Si no hay ID en la URL, verificamos si hay un simulacro activo en el backend
                if (!activeId) {
                    const activeRes = await getActiveExam();
                    if (activeRes?.data?.attempt?.id) {
                        activeId = activeRes.data.attempt.id;
                        setExamId(activeId);
                    } else {
                        // Sin examen activo ni ID, enviar a configuración
                        navigate("/mock/config", { replace: true });
                        return;
                    }
                }

                const response = await getExamAttempt(activeId);

                if (!isMounted) return;

                const data = response?.data || response;
                const attempt = data?.attempt;
                const receivedQuestions = data?.questions || [];

                if (!attempt || !receivedQuestions.length) {
                    throw new Error("No se encontraron preguntas para este simulacro.");
                }

                // Si el simulacro ya finalizó o expiró, redirigir a resultados
                if (attempt.status === "completed") {
                    navigate(`/mock/result?id=${attempt.id}`, { replace: true });
                    return;
                }

                setAttemptData(attempt);
                setQuestions(receivedQuestions);
                setExamId(attempt.id);

                // Inicializar respuestas previas recuperadas del intento
                const initialAnswers = {};
                const initialTimePerQuestion = {};
                if (data.answers && typeof data.answers === "object") {
                    Object.entries(data.answers).forEach(([qId, ansObj]) => {
                        if (ansObj?.selected_option_id) {
                            initialAnswers[qId] = {
                                selected_option_id: ansObj.selected_option_id,
                                selected_option_letter: ansObj.selected_option_letter,
                            };
                        }
                        if (ansObj?.time_spent_seconds) {
                            initialTimePerQuestion[qId] = ansObj.time_spent_seconds;
                        }
                    });
                }
                setAnswers(initialAnswers);
                setTimePerQuestion(initialTimePerQuestion);

                // Inicializar preguntas marcadas
                const flaggedList = data.flagged_questions || attempt.flagged_question_ids || [];
                setFlaggedQuestions(new Set(flaggedList));

                // Sincronizar temporizador con los segundos reales restantes en el servidor
                const remaining = typeof attempt.remaining_seconds === "number"
                    ? attempt.remaining_seconds
                    : attempt.duration_minutes * 60;
                setTimeLeft(Math.max(0, remaining));

                questionStartTimeRef.current = Date.now();
            } catch (err) {
                if (!isMounted) return;
                console.error("ERROR AL CARGAR SIMULACRO:", err);
                setError(
                    err?.response?.data?.message ||
                        err?.message ||
                        "No se pudo cargar el simulacro oficial."
                );
            } finally {
                if (isMounted) setLoading(false);
            }
        };

        loadExam();

        return () => {
            isMounted = false;
        };
    }, [urlExamId, navigate]);

    /* ============================================================
     * TEMPORIZADOR DE EXAMEN CON AUTO-FINALIZACIÓN
     * ============================================================ */
    useEffect(() => {
        if (loading || timeLeft === null || finishing) return;

        if (timeLeft <= 0) {
            // Tiempo expirado: enviar automáticamente al backend
            handleAutoFinish();
            return;
        }

        const timer = setInterval(() => {
            setTimeLeft((prev) => {
                if (prev <= 1) {
                    clearInterval(timer);
                    handleAutoFinish();
                    return 0;
                }
                return prev - 1;
            });
        }, 1000);

        return () => clearInterval(timer);
    }, [loading, timeLeft, finishing]);

    /* ============================================================
     * FINALIZACIÓN AUTOMÁTICA POR TIEMPO EXPIRADO
     * ============================================================ */
    const handleAutoFinish = useCallback(async () => {
        if (!examId || finishing) return;
        try {
            setFinishing(true);
            await finishExamAttempt(examId);
            navigate(`/mock/result?id=${examId}&expired=true`, { replace: true });
        } catch (err) {
            console.error("Error al finalizar simulacro por tiempo expirado:", err);
            navigate(`/mock/result?id=${examId}`, { replace: true });
        }
    }, [examId, finishing, navigate]);

    /* ============================================================
     * FORMATEAR TIEMPO HH:MM:SS
     * ============================================================ */
    const formatTime = (seconds) => {
        if (seconds === null || seconds === undefined) return "--:--";
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        if (h > 0) {
            return `${String(h).padStart(2, "0")}:${String(m).padStart(2, "0")}:${String(s).padStart(2, "0")}`;
        }
        return `${String(m).padStart(2, "0")}:${String(s).padStart(2, "0")}`;
    };

    /* ============================================================
     * PREGUNTA Y ESTÍMULO ACTUAL
     * ============================================================ */
    const question = questions[currentQuestionIndex] || null;
    const prevQuestion = currentQuestionIndex > 0 ? questions[currentQuestionIndex - 1] : null;

    const currentStimulus = question?.stimulus || null;
    const isSameStimulusAsPrevious = Boolean(
        currentStimulus &&
        prevQuestion?.stimulus &&
        currentStimulus.id === prevQuestion.stimulus.id
    );

    // Obtener imagen confirmada del estímulo si existe
    const stimulusMediaPath = useMemo(() => {
        if (!currentStimulus) return null;
        const confirmed = currentStimulus.confirmed_media_assets;
        if (Array.isArray(confirmed) && confirmed.length > 0) {
            return confirmed[0]?.file_path || null;
        }
        return null;
    }, [currentStimulus]);

    // Obtener imágenes confirmadas a nivel de pregunta
    const questionMediaPath = useMemo(() => {
        if (!question) return null;
        const confirmed = question.confirmed_media_assets;
        if (Array.isArray(confirmed) && confirmed.length > 0) {
            return confirmed[0]?.file_path || null;
        }
        return null;
    }, [question]);

    /* ============================================================
     * CALCULAR TIEMPO INVERTIDO EN LA PREGUNTA ACTUAL
     * ============================================================ */
    const getElapsedSecondsOnCurrent = () => {
        const now = Date.now();
        const diff = Math.floor((now - questionStartTimeRef.current) / 1000);
        return Math.max(1, diff);
    };

    /* ============================================================
     * SELECCIONAR OPCIÓN (RESPUESTA)
     * ============================================================ */
    const handleSelectOption = async (option) => {
        if (!question || !examId || saving || finishing) return;

        const qId = question.id;
        const optId = option.id;
        const optLetter = option.option_letter || "";

        const elapsed = getElapsedSecondsOnCurrent();
        const prevSpent = timePerQuestion[qId] || 0;
        const totalSpent = prevSpent + elapsed;

        // Actualización optimista de estado local
        setAnswers((prev) => ({
            ...prev,
            [qId]: {
                selected_option_id: optId,
                selected_option_letter: optLetter,
            },
        }));

        setTimePerQuestion((prev) => ({
            ...prev,
            [qId]: totalSpent,
        }));

        questionStartTimeRef.current = Date.now();

        try {
            setSaving(true);
            setError("");

            await saveExamAnswer(examId, {
                question_id: qId,
                selected_option_id: optId,
                selected_option_letter: optLetter,
                time_spent_seconds: totalSpent,
                flagged: flaggedQuestions.has(qId),
            });
        } catch (err) {
            console.error("Error al guardar respuesta:", err);
            // No bloqueamos al usuario si hay un hipo de red menor
        } finally {
            setSaving(false);
        }
    };

    /* ============================================================
     * MARCAR / DESMARCAR PARA REVISAR (FLAG)
     * ============================================================ */
    const handleToggleFlag = async () => {
        if (!question || !examId || saving || finishing) return;

        const qId = question.id;
        const willBeFlagged = !flaggedQuestions.has(qId);

        setFlaggedQuestions((prev) => {
            const next = new Set(prev);
            if (willBeFlagged) {
                next.add(qId);
            } else {
                next.delete(qId);
            }
            return next;
        });

        const currentAnswer = answers[qId];

        try {
            await saveExamAnswer(examId, {
                question_id: qId,
                selected_option_id: currentAnswer?.selected_option_id || null,
                selected_option_letter: currentAnswer?.selected_option_letter || null,
                time_spent_seconds: timePerQuestion[qId] || 0,
                flagged: willBeFlagged,
            });
        } catch (err) {
            console.error("Error al actualizar marca de revisión:", err);
        }
    };

    /* ============================================================
     * NAVEGACIÓN ENTRE PREGUNTAS
     * ============================================================ */
    const handleGoToQuestion = (targetIndex) => {
        if (targetIndex < 0 || targetIndex >= questions.length) return;

        // Acumular tiempo transcurrido en la pregunta que dejamos
        if (question) {
            const elapsed = getElapsedSecondsOnCurrent();
            setTimePerQuestion((prev) => ({
                ...prev,
                [question.id]: (prev[question.id] || 0) + elapsed,
            }));
        }

        questionStartTimeRef.current = Date.now();
        setCurrentQuestionIndex(targetIndex);
        window.scrollTo({ top: 0, behavior: "smooth" });
    };

    const handleNext = () => {
        if (currentQuestionIndex < questions.length - 1) {
            handleGoToQuestion(currentQuestionIndex + 1);
        }
    };

    const handlePrevious = () => {
        if (currentQuestionIndex > 0) {
            handleGoToQuestion(currentQuestionIndex - 1);
        }
    };

    /* ============================================================
     * FINALIZAR Y CALIFICAR EN BACKEND
     * ============================================================ */
    const handleConfirmFinish = async () => {
        if (!examId || finishing) return;

        try {
            setFinishing(true);
            setIsFinishModalOpen(false);
            setError("");

            const res = await finishExamAttempt(examId);
            console.log("SIMULACRO FINALIZADO Y CALIFICADO:", res);

            navigate(`/mock/result?id=${examId}`);
        } catch (err) {
            console.error("ERROR AL FINALIZAR SIMULACRO:", err);
            setError(
                err?.response?.data?.message ||
                    err?.message ||
                    "No se pudo finalizar el simulacro. Inténtalo nuevamente."
            );
            setFinishing(false);
        }
    };

    /* ============================================================
     * LIGHTBOX HANDLERS
     * ============================================================ */
    const openLightbox = (src, alt, caption) => {
        setLightboxState({
            isOpen: true,
            src,
            alt: alt || "Recurso visual",
            caption: caption || "",
        });
    };

    const closeLightbox = () => {
        setLightboxState((prev) => ({ ...prev, isOpen: false }));
    };

    /* ============================================================
     * RESUMEN DE PROGRESO PARA NAVEGACIÓN Y MODAL
     * ============================================================ */
    const answeredCount = Object.keys(answers).length;
    const unansweredCount = Math.max(0, questions.length - answeredCount);
    const flaggedCount = flaggedQuestions.size;
    const isCurrentFlagged = question ? flaggedQuestions.has(question.id) : false;
    const isLastQuestion = currentQuestionIndex === questions.length - 1;
    const currentSelectedOptionId = question ? answers[question.id]?.selected_option_id : null;

    const progressPercentage = questions.length > 0
        ? Math.round(((currentQuestionIndex + 1) / questions.length) * 100)
        : 0;

    /* ============================================================
     * VISTAS DE CARGA Y ERROR
     * ============================================================ */
    if (loading) {
        return (
            <div className="main__content" style={{ maxWidth: "860px", margin: "0 auto", padding: "40px 20px" }}>
                <section className="question" style={{ textAlign: "center", padding: "60px 24px" }}>
                    <div style={{ fontSize: "38px", color: "#38bdf8", marginBottom: "18px" }}>
                        <i className="fas fa-spinner fa-spin"></i>
                    </div>
                    <h1 className="question__title" style={{ fontSize: "22px", marginBottom: "10px" }}>
                        Cargando tu Simulacro ICFES...
                    </h1>
                    <p style={{ color: "#94a3b8" }}>
                        Preparando el cuadernillo estructurado, textos y reactivos multimedia.
                    </p>
                </section>
            </div>
        );
    }

    if (error && questions.length === 0) {
        return (
            <div className="main__content" style={{ maxWidth: "860px", margin: "0 auto", padding: "40px 20px" }}>
                <section className="question" style={{ textAlign: "center", padding: "60px 24px" }}>
                    <div style={{ fontSize: "38px", color: "#ef4444", marginBottom: "18px" }}>
                        <i className="fas fa-triangle-exclamation"></i>
                    </div>
                    <h1 className="question__title" style={{ fontSize: "22px", marginBottom: "10px" }}>
                        No se pudo iniciar el simulacro
                    </h1>
                    <p style={{ color: "#94a3b8", marginBottom: "24px" }}>{error}</p>
                    <button
                        type="button"
                        className="footer-btn footer-btn--primary"
                        onClick={() => navigate("/mock/config")}
                    >
                        Volver a Configuración de Simulacros
                    </button>
                </section>
            </div>
        );
    }

    if (!question) {
        return (
            <div className="main__content" style={{ maxWidth: "860px", margin: "0 auto", padding: "40px 20px" }}>
                <section className="question" style={{ textAlign: "center", padding: "60px 24px" }}>
                    <h1 className="question__title">No hay preguntas disponibles en este simulacro.</h1>
                    <button
                        type="button"
                        className="footer-btn footer-btn--primary"
                        onClick={() => navigate("/mock/config")}
                    >
                        Configurar Nuevo Simulacro
                    </button>
                </section>
            </div>
        );
    }

    const options = question.options || [];

    return (
        <>
            <div className="main__content" style={{ maxWidth: "900px", margin: "0 auto", padding: "20px 24px 60px" }}>

                {/* TOP BAR: Materia, Indicador de Pregunta y Temporizador */}
                <section className="quiz-top">
                    <div className="quiz-top__left">
                        <span className="quiz-top__subject">
                            {attemptData?.subject_name || question?.subject?.name || "LECTURA CRÍTICA"}
                            <span style={{ color: "#94a3b8", fontWeight: 400, marginLeft: "8px" }}>
                                · MODO SIMULACRO OFICIAL
                            </span>
                        </span>
                        <h2>
                            Pregunta {currentQuestionIndex + 1} de {questions.length}
                        </h2>
                    </div>

                    <div className={`quiz-top__timer ${timeLeft !== null && timeLeft <= 300 ? "timer--warning" : ""}`}
                         style={timeLeft !== null && timeLeft <= 300 ? { borderColor: "#ef4444", color: "#f87171" } : {}}>
                        <i className="fa-regular fa-clock" style={timeLeft !== null && timeLeft <= 300 ? { color: "#ef4444" } : {}}></i>
                        <span style={{ fontVariantNumeric: "tabular-nums" }}>
                            {formatTime(timeLeft)}
                        </span>
                    </div>
                </section>

                {/* BARRA DE PROGRESO */}
                <div className="progress" style={{ marginBottom: "14px" }}>
                    <div
                        className="progress__fill"
                        style={{
                            width: `${progressPercentage}%`,
                            transition: "width 0.25s ease",
                        }}
                    ></div>
                </div>

                {/* STRIP DE NAVEGACIÓN RÁPIDA (1 a N) CON ESTADOS: ACTIVA, RESPONDIDA, MARCADA, PENDIENTE */}
                <div
                    style={{
                        display: "flex",
                        alignItems: "center",
                        gap: "6px",
                        overflowX: "auto",
                        padding: "8px 2px 14px",
                        marginBottom: "18px",
                        scrollbarWidth: "thin",
                    }}
                >
                    {questions.map((q, idx) => {
                        const isCurrent = idx === currentQuestionIndex;
                        const isAnswered = Boolean(answers[q.id]?.selected_option_id);
                        const isFlagged = flaggedQuestions.has(q.id);

                        let bg = "rgba(15, 23, 42, 0.75)";
                        let border = "rgba(148, 163, 184, 0.25)";
                        let color = "#94a3b8";

                        if (isAnswered) {
                            bg = "rgba(59, 130, 246, 0.22)";
                            border = "#3b82f6";
                            color = "#93c5fd";
                        }

                        if (isFlagged) {
                            border = "#f59e0b";
                            color = "#fbbf24";
                        }

                        if (isCurrent) {
                            bg = "rgba(56, 189, 248, 0.35)";
                            border = "#38bdf8";
                            color = "#ffffff";
                        }

                        return (
                            <button
                                key={q.id}
                                type="button"
                                onClick={() => handleGoToQuestion(idx)}
                                style={{
                                    minWidth: "34px",
                                    height: "32px",
                                    borderRadius: "6px",
                                    border: `1.5px solid ${border}`,
                                    backgroundColor: bg,
                                    color: color,
                                    fontSize: "12px",
                                    fontWeight: isCurrent ? 700 : 500,
                                    cursor: "pointer",
                                    display: "inline-flex",
                                    alignItems: "center",
                                    justifyContent: "center",
                                    position: "relative",
                                    flexShrink: 0,
                                    transition: "all 0.15s ease",
                                }}
                                title={`Pregunta ${idx + 1}: ${isAnswered ? "Respondida" : "Sin responder"}${isFlagged ? " (Marcada)" : ""}`}
                            >
                                {idx + 1}
                                {isFlagged && (
                                    <span
                                        style={{
                                            position: "absolute",
                                            top: "-4px",
                                            right: "-4px",
                                            fontSize: "8px",
                                            color: "#f59e0b",
                                        }}
                                    >
                                        <i className="fas fa-flag"></i>
                                    </span>
                                )}
                            </button>
                        );
                    })}
                </div>

                {/* MENSAJE DE ERROR */}
                {error && (
                    <div style={{
                        padding: "12px 16px",
                        backgroundColor: "rgba(239, 68, 68, 0.15)",
                        border: "1px solid #ef4444",
                        borderRadius: "10px",
                        color: "#fca5a5",
                        fontSize: "14px",
                        marginBottom: "16px",
                    }}>
                        <i className="fas fa-circle-exclamation" style={{ marginRight: "8px" }}></i>
                        {error}
                    </div>
                )}

                {/* ============================================================
                 * PANEL DE ESTÍMULO (TEXTO / CARICATURA / HISTORIETA / INFOGRAFÍA)
                 * ============================================================ */}
                {currentStimulus && (
                    <section
                        style={{
                            backgroundColor: "rgba(15, 23, 42, 0.8)",
                            border: "1px solid rgba(148, 163, 184, 0.2)",
                            borderRadius: "14px",
                            padding: "18px 20px",
                            marginBottom: "24px",
                        }}
                    >
                        <div
                            onClick={() => setStimulusCollapsed(!stimulusCollapsed)}
                            style={{
                                display: "flex",
                                alignItems: "center",
                                justifyContent: "space-between",
                                cursor: "pointer",
                                gap: "10px",
                            }}
                        >
                            <div style={{ display: "flex", alignItems: "center", gap: "10px", flexWrap: "wrap" }}>
                                <span style={{
                                    backgroundColor: "rgba(56, 189, 248, 0.15)",
                                    border: "1px solid rgba(56, 189, 248, 0.4)",
                                    color: "#38bdf8",
                                    fontSize: "11px",
                                    fontWeight: 700,
                                    padding: "4px 10px",
                                    borderRadius: "999px",
                                    letterSpacing: "0.5px",
                                }}>
                                    <i className="fas fa-book-open" style={{ marginRight: "6px" }}></i>
                                    TEXTO DE LECTURA
                                </span>
                                <h3 style={{ fontSize: "15px", fontWeight: 700, margin: 0, color: "#f8fafc" }}>
                                    {currentStimulus.title || "Lectura del Cuadernillo"}
                                </h3>
                                {currentStimulus.source_page && (
                                    <span style={{ fontSize: "12px", color: "#38bdf8", fontWeight: 600 }}>
                                        · Pág. {currentStimulus.source_page}
                                    </span>
                                )}
                                {isSameStimulusAsPrevious && (
                                    <span style={{ fontSize: "12px", color: "#94a3b8" }}>
                                        (Continúa de la pregunta anterior)
                                    </span>
                                )}
                            </div>

                            <button
                                type="button"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    setStimulusCollapsed(!stimulusCollapsed);
                                }}
                                style={{
                                    background: "none",
                                    border: "none",
                                    color: "#94a3b8",
                                    fontSize: "12px",
                                    cursor: "pointer",
                                    display: "flex",
                                    alignItems: "center",
                                    gap: "6px",
                                }}
                            >
                                {stimulusCollapsed ? "Mostrar lectura" : "Ocultar"}
                                <i className={`fas ${stimulusCollapsed ? "fa-chevron-down" : "fa-chevron-up"}`}></i>
                            </button>
                        </div>

                        {!stimulusCollapsed && (
                            <div style={{ marginTop: "16px", borderTop: "1px solid rgba(148, 163, 184, 0.15)", paddingTop: "14px" }}>
                                {currentStimulus.instructions && (
                                    <div style={{
                                        fontSize: "13px",
                                        fontStyle: "italic",
                                        color: "#cbd5e1",
                                        backgroundColor: "rgba(30, 41, 59, 0.6)",
                                        padding: "8px 12px",
                                        borderRadius: "8px",
                                        marginBottom: "14px",
                                        borderLeft: "3px solid #38bdf8",
                                    }}>
                                        <i className="fas fa-circle-info" style={{ marginRight: "6px", color: "#38bdf8" }}></i>
                                        {currentStimulus.instructions}
                                    </div>
                                )}

                                {/* RECURSO VISUAL CONFIRMADO DEL ESTÍMULO (HISTORIETAS, INFOGRAFÍAS) */}
                                {stimulusMediaPath && (
                                    <div style={{ margin: "14px 0", textAlign: "center" }}>
                                        <div
                                            onClick={() =>
                                                openLightbox(
                                                    getStudentMediaUrl(stimulusMediaPath),
                                                    currentStimulus.title,
                                                    `Recurso visual de la página ${currentStimulus.source_page || "cuadernillo"}`
                                                )
                                            }
                                            style={{
                                                display: "inline-block",
                                                cursor: "zoom-in",
                                                borderRadius: "10px",
                                                overflow: "hidden",
                                                background: "#ffffff",
                                                padding: "6px",
                                                border: "1px solid rgba(148, 163, 184, 0.3)",
                                                maxWidth: "100%",
                                            }}
                                            title="Haz clic para ampliar la imagen"
                                        >
                                            <img
                                                src={getStudentMediaUrl(stimulusMediaPath)}
                                                alt={currentStimulus.title || "Recurso gráfico"}
                                                style={{
                                                    maxWidth: "100%",
                                                    maxHeight: "360px",
                                                    objectFit: "contain",
                                                    display: "block",
                                                }}
                                            />
                                            <span style={{ display: "block", fontSize: "11px", color: "#475569", marginTop: "4px" }}>
                                                <i className="fas fa-magnifying-glass-plus"></i> Clic para ampliar en pantalla completa
                                            </span>
                                        </div>
                                    </div>
                                )}

                                {/* TEXTO ESCRITO DEL ESTÍMULO */}
                                {currentStimulus.content_text && (
                                    <div style={{
                                        color: "#e2e8f0",
                                        fontSize: "15px",
                                        lineHeight: "1.75",
                                        whiteSpace: "pre-line",
                                        textAlign: "justify",
                                    }}>
                                        {currentStimulus.content_text}
                                    </div>
                                )}
                            </div>
                        )}
                    </section>
                )}

                {/* ============================================================
                 * PREGUNTA Y ENUNCIADO
                 * ============================================================ */}
                <section className="question" style={{ marginBottom: "28px" }}>
                    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", gap: "12px", marginBottom: "16px" }}>
                        <h1 className="question__title" style={{ margin: 0, fontSize: "18px", lineHeight: "1.6" }}>
                            {question.statement || question.question || "Pregunta sin enunciado"}
                        </h1>

                        {/* BOTÓN: MARCAR PARA REVISAR */}
                        <button
                            type="button"
                            onClick={handleToggleFlag}
                            style={{
                                display: "inline-flex",
                                alignItems: "center",
                                gap: "6px",
                                backgroundColor: isCurrentFlagged ? "rgba(245, 158, 11, 0.2)" : "rgba(30, 41, 59, 0.7)",
                                border: `1px solid ${isCurrentFlagged ? "#f59e0b" : "rgba(148, 163, 184, 0.25)"}`,
                                color: isCurrentFlagged ? "#fbbf24" : "#94a3b8",
                                borderRadius: "8px",
                                padding: "6px 12px",
                                fontSize: "12px",
                                fontWeight: 600,
                                cursor: "pointer",
                                flexShrink: 0,
                                transition: "all 0.15s ease",
                            }}
                            title="Marca esta pregunta para revisarla antes de finalizar"
                        >
                            <i className="fas fa-flag"></i>
                            {isCurrentFlagged ? "Marcada" : "Marcar para revisar"}
                        </button>
                    </div>

                    {/* IMAGEN A NIVEL DE PREGUNTA SI APLICA */}
                    {questionMediaPath && (
                        <div style={{ margin: "16px 0", textAlign: "center" }}>
                            <div
                                onClick={() =>
                                    openLightbox(
                                        getStudentMediaUrl(questionMediaPath),
                                        "Recurso de la pregunta",
                                        "Imagen asociada a la pregunta"
                                    )
                                }
                                style={{
                                    display: "inline-block",
                                    cursor: "zoom-in",
                                    borderRadius: "10px",
                                    overflow: "hidden",
                                    background: "#ffffff",
                                    padding: "6px",
                                    border: "1px solid rgba(148, 163, 184, 0.3)",
                                }}
                            >
                                <img
                                    src={getStudentMediaUrl(questionMediaPath)}
                                    alt="Recurso de la pregunta"
                                    style={{
                                        maxWidth: "100%",
                                        maxHeight: "300px",
                                        objectFit: "contain",
                                        display: "block",
                                    }}
                                />
                            </div>
                        </div>
                    )}

                    {/* ============================================================
                     * OPCIONES (A, B, C, D) — CERO RETROALIMENTACIÓN DURANTE EXAMEN
                     * ============================================================ */}
                    <div className="options" style={{ marginTop: "20px" }}>
                        {options.map((opt, index) => {
                            const optId = opt.id;
                            const isSelected = currentSelectedOptionId === optId;
                            const letter = opt.option_letter || String.fromCharCode(65 + index);

                            return (
                                <button
                                    key={optId || index}
                                    type="button"
                                    onClick={() => handleSelectOption(opt)}
                                    disabled={saving || finishing}
                                    className={`option ${isSelected ? "option--selected" : ""}`}
                                    style={isSelected ? {
                                        borderColor: "#38bdf8",
                                        backgroundColor: "rgba(56, 189, 248, 0.14)",
                                        boxShadow: "0 0 0 1px rgba(56, 189, 248, 0.3)",
                                    } : {}}
                                >
                                    <span
                                        className="option__letter"
                                        style={isSelected ? {
                                            backgroundColor: "#0284c7",
                                            color: "#ffffff",
                                            borderColor: "#38bdf8",
                                        } : {}}
                                    >
                                        {letter}
                                    </span>
                                    <span style={{ flex: 1, textAlign: "left", lineHeight: "1.5" }}>
                                        {opt.option_text || opt.text || ""}
                                    </span>
                                </button>
                            );
                        })}
                    </div>

                    {saving && (
                        <div style={{ fontSize: "12px", color: "#38bdf8", marginTop: "10px", display: "flex", alignItems: "center", gap: "6px" }}>
                            <i className="fas fa-spinner fa-spin"></i> Guardando selección...
                        </div>
                    )}
                </section>

                {/* ============================================================
                 * FOOTER DE NAVEGACIÓN Y FINALIZACIÓN
                 * ============================================================ */}
                <section className="quiz-footer" style={{ marginTop: "32px", display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                    <button
                        type="button"
                        className="footer-btn footer-btn--ghost"
                        onClick={handlePrevious}
                        disabled={currentQuestionIndex === 0 || finishing}
                    >
                        <i className="fas fa-chevron-left"></i>
                        Anterior
                    </button>

                    <div style={{ display: "flex", gap: "10px" }}>
                        {!isLastQuestion ? (
                            <button
                                type="button"
                                className="footer-btn footer-btn--primary"
                                onClick={handleNext}
                                disabled={finishing}
                            >
                                Siguiente
                                <i className="fas fa-chevron-right"></i>
                            </button>
                        ) : null}

                        <button
                            type="button"
                            onClick={() => setIsFinishModalOpen(true)}
                            disabled={finishing}
                            style={{
                                backgroundColor: "#0284c7",
                                color: "#ffffff",
                                border: "none",
                                borderRadius: "12px",
                                padding: "12px 20px",
                                fontWeight: 700,
                                fontSize: "14px",
                                cursor: "pointer",
                                display: "inline-flex",
                                alignItems: "center",
                                gap: "8px",
                                transition: "all 0.15s ease",
                            }}
                        >
                            <i className="fas fa-flag-checkered"></i>
                            Finalizar Simulacro
                        </button>
                    </div>
                </section>
            </div>

            {/* ASISTENTE ZORRITO FLOTANTE */}
            <div className="assistant">
                <div className="assistant__message">
                    {saving
                        ? "Guardando tu respuesta..."
                        : isCurrentFlagged
                        ? "Esta pregunta está marcada para que la revises antes de entregar."
                        : isLastQuestion
                        ? "¡Última pregunta! Revisa todas antes de finalizar."
                        : `Pregunta ${currentQuestionIndex + 1} de ${questions.length}. Mantén la concentración.`}
                </div>
                <div className="assistant__bot">
                    <img src={Zorrito} alt="Zorrito Athena" />
                </div>
            </div>

            {/* LIGHTBOX PARA ZOOM DE IMÁGENES */}
            <PracticeLightbox
                isOpen={lightboxState.isOpen}
                onClose={closeLightbox}
                src={lightboxState.src}
                alt={lightboxState.alt}
                caption={lightboxState.caption}
            />

            {/* ============================================================
             * MODAL DE CONFIRMACIÓN PARA FINALIZAR SIMULACRO
             * ============================================================ */}
            {isFinishModalOpen && (
                <div
                    style={{
                        position: "fixed",
                        top: 0,
                        left: 0,
                        right: 0,
                        bottom: 0,
                        backgroundColor: "rgba(10, 15, 30, 0.85)",
                        zIndex: 9999,
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "center",
                        padding: "20px",
                    }}
                    onClick={() => !finishing && setIsFinishModalOpen(false)}
                >
                    <div
                        onClick={(e) => e.stopPropagation()}
                        style={{
                            backgroundColor: "#162032",
                            border: "1px solid rgba(148, 163, 184, 0.25)",
                            borderRadius: "18px",
                            padding: "28px",
                            maxWidth: "500px",
                            width: "100%",
                            boxShadow: "0 20px 40px rgba(0, 0, 0, 0.5)",
                        }}
                    >
                        <div style={{ textAlign: "center", marginBottom: "20px" }}>
                            <div style={{
                                width: "60px",
                                height: "60px",
                                borderRadius: "50%",
                                backgroundColor: unansweredCount > 0 ? "rgba(245, 158, 11, 0.2)" : "rgba(34, 197, 94, 0.2)",
                                color: unansweredCount > 0 ? "#f59e0b" : "#22c55e",
                                display: "inline-flex",
                                alignItems: "center",
                                justifyContent: "center",
                                fontSize: "26px",
                                marginBottom: "12px",
                            }}>
                                <i className={unansweredCount > 0 ? "fas fa-triangle-exclamation" : "fas fa-circle-check"}></i>
                            </div>
                            <h2 style={{ fontSize: "20px", fontWeight: 700, margin: 0, color: "#f8fafc" }}>
                                ¿Deseas finalizar el simulacro?
                            </h2>
                            <p style={{ color: "#94a3b8", fontSize: "14px", marginTop: "6px" }}>
                                Una vez finalizado, se calculará tu puntaje oficial y podrás revisar cada respuesta.
                            </p>
                        </div>

                        {/* RESUMEN DEL ESTADO DE PREGUNTAS */}
                        <div style={{
                            display: "grid",
                            gridTemplateColumns: "1fr 1fr 1fr",
                            gap: "10px",
                            backgroundColor: "rgba(15, 23, 42, 0.6)",
                            padding: "14px",
                            borderRadius: "12px",
                            marginBottom: "20px",
                            textAlign: "center",
                        }}>
                            <div>
                                <span style={{ fontSize: "20px", fontWeight: 700, color: "#38bdf8", display: "block" }}>
                                    {answeredCount}
                                </span>
                                <span style={{ fontSize: "12px", color: "#94a3b8" }}>Respondidas</span>
                            </div>
                            <div>
                                <span style={{ fontSize: "20px", fontWeight: 700, color: unansweredCount > 0 ? "#f87171" : "#94a3b8", display: "block" }}>
                                    {unansweredCount}
                                </span>
                                <span style={{ fontSize: "12px", color: "#94a3b8" }}>Sin responder</span>
                            </div>
                            <div>
                                <span style={{ fontSize: "20px", fontWeight: 700, color: "#fbbf24", display: "block" }}>
                                    {flaggedCount}
                                </span>
                                <span style={{ fontSize: "12px", color: "#94a3b8" }}>Marcadas</span>
                            </div>
                        </div>

                        {unansweredCount > 0 && (
                            <div style={{
                                padding: "10px 14px",
                                backgroundColor: "rgba(245, 158, 11, 0.15)",
                                border: "1px solid rgba(245, 158, 11, 0.3)",
                                borderRadius: "8px",
                                color: "#fbbf24",
                                fontSize: "13px",
                                marginBottom: "20px",
                            }}>
                                <i className="fas fa-info-circle" style={{ marginRight: "6px" }}></i>
                                Tienes {unansweredCount} pregunta(s) sin responder. Las preguntas no contestadas contarán como omitidas.
                            </div>
                        )}

                        <div style={{ display: "flex", gap: "10px", justifyContent: "flex-end" }}>
                            <button
                                type="button"
                                onClick={() => setIsFinishModalOpen(false)}
                                disabled={finishing}
                                style={{
                                    backgroundColor: "transparent",
                                    border: "1px solid rgba(148, 163, 184, 0.3)",
                                    color: "#cbd5e1",
                                    padding: "10px 18px",
                                    borderRadius: "10px",
                                    fontSize: "14px",
                                    fontWeight: 600,
                                    cursor: "pointer",
                                }}
                            >
                                Continuar respondiendo
                            </button>

                            <button
                                type="button"
                                onClick={handleConfirmFinish}
                                disabled={finishing}
                                style={{
                                    backgroundColor: "#0284c7",
                                    border: "none",
                                    color: "#ffffff",
                                    padding: "10px 20px",
                                    borderRadius: "10px",
                                    fontSize: "14px",
                                    fontWeight: 700,
                                    cursor: "pointer",
                                    display: "inline-flex",
                                    alignItems: "center",
                                    gap: "8px",
                                }}
                            >
                                {finishing ? (
                                    <>
                                        <i className="fas fa-spinner fa-spin"></i>
                                        Calificando...
                                    </>
                                ) : (
                                    <>
                                        <i className="fas fa-check"></i>
                                        Sí, entregar y calificar
                                    </>
                                )}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
};

export default MockExam;
