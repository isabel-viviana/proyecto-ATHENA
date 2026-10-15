import "./practice_full.css";
import { useEffect, useState } from "react";
import { useSearchParams, useNavigate, Link } from "react-router-dom";
import Zorrito from "../../assets/images/Zorrito2D.png";
import { getExamQuestions, submitAnswer, finishExam } from "../../api/exams";

const PracticeFull = () => {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();

    // ID del intento de práctica generado en practice_config
    const practiceId = searchParams.get("id");

    const [questions, setQuestions] = useState([]);
    const [currentQuestion, setCurrentQuestion] = useState(0);
    const [answers, setAnswers] = useState({});

    // Control de colapso de estímulo compartido
    const [stimulusCollapsed, setStimulusCollapsed] = useState(false);

    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [finishing, setFinishing] = useState(false);
    const [error, setError] = useState("");

    // Temporizador de sesión de práctica (segundos transcurridos)
    const [secondsElapsed, setSecondsElapsed] = useState(0);

    /* ============================================================
     * CARGAR PREGUNTAS DESDE EL BACKEND
     * ============================================================ */
    useEffect(() => {
        const loadPracticeQuestions = async () => {
            if (!practiceId) {
                setError(
                    "No encontramos el identificador de la práctica. Selecciona un área para comenzar."
                );
                setLoading(false);
                return;
            }

            try {
                setLoading(true);
                setError("");

                const response = await getExamQuestions(practiceId);

                const receivedQuestions =
                    response?.data?.questions ||
                    response?.questions ||
                    response?.data ||
                    [];

                if (!Array.isArray(receivedQuestions) || receivedQuestions.length === 0) {
                    throw new Error(
                        "No se encontraron preguntas disponibles para esta práctica en la base de datos."
                    );
                }

                setQuestions(receivedQuestions);

                // Inicializar respuestas previas si el intento ya tenía progreso registrado
                const preselected = {};
                receivedQuestions.forEach((q) => {
                    if (q.selected_option_id) {
                        preselected[q.id] = q.selected_option_id;
                    }
                });
                setAnswers(preselected);
            } catch (err) {
                console.error("Error al cargar preguntas de práctica:", err);
                setError(
                    err?.response?.data?.message ||
                        err?.message ||
                        "No se pudieron cargar las preguntas del banco."
                );
            } finally {
                setLoading(false);
            }
        };

        loadPracticeQuestions();
    }, [practiceId]);

    /* ============================================================
     * TEMPORIZADOR DE PRÁCTICA
     * ============================================================ */
    useEffect(() => {
        if (loading || questions.length === 0) return;

        const timer = setInterval(() => {
            setSecondsElapsed((prev) => prev + 1);
        }, 1000);

        return () => clearInterval(timer);
    }, [loading, questions.length]);

    const formatTimer = (totalSeconds) => {
        const mins = Math.floor(totalSeconds / 60);
        const secs = totalSeconds % 60;
        return `${String(mins).padStart(2, "0")}:${String(secs).padStart(2, "0")}`;
    };

    /* ============================================================
     * DETECCIÓN DE ESTÍMULO CONSECUTIVO (OPTIMIZACIÓN DE SCROLL)
     * ============================================================ */
    const question = questions[currentQuestion];
    const prevQuestion = currentQuestion > 0 ? questions[currentQuestion - 1] : null;

    const isSameStimulusAsPrevious =
        question?.stimulus &&
        prevQuestion?.stimulus &&
        question.stimulus.id === prevQuestion.stimulus.id;

    // Al cambiar de pregunta, colapsar automáticamente solo si es continuación del mismo texto
    useEffect(() => {
        if (isSameStimulusAsPrevious) {
            setStimulusCollapsed(true);
        } else {
            setStimulusCollapsed(false);
        }
    }, [currentQuestion]);

    /* ============================================================
     * HELPERS DE CONTENIDO E IMÁGENES
     * ============================================================ */
    const getQuestionText = (q) => {
        return (
            q?.statement ||
            q?.question ||
            q?.question_text ||
            q?.text ||
            "Pregunta sin texto registrado."
        );
    };

    const getOptions = (q) => {
        return q?.options || q?.question_options || [];
    };

    const getOptionText = (opt) => {
        return opt?.option_text || opt?.text || opt?.content || "";
    };

    const resolveMediaUrl = (url) => {
        if (!url) return null;
        if (url.startsWith("http://") || url.startsWith("https://") || url.startsWith("data:")) {
            return url;
        }
        const cleaned = url.replace(/^\/?storage\//, "");
        return `/storage/${cleaned}`;
    };

    /* ============================================================
     * REGISTRO DE RESPUESTAS
     * ============================================================ */
    const handleSelectOption = async (option) => {
        if (!question || saving || finishing) return;

        const questionId = question.id;
        const optionId = option.id;

        if (!questionId || !optionId) return;

        // Actualizar estado visual inmediato
        setAnswers((prev) => ({
            ...prev,
            [questionId]: optionId,
        }));

        try {
            setSaving(true);
            setError("");

            await submitAnswer(practiceId, {
                question_id: questionId,
                selected_option_id: optionId,
                time_spent_seconds: 0,
            });
        } catch (err) {
            console.error("Error al registrar respuesta de práctica:", err);
            setError(
                err?.response?.data?.message ||
                    err?.message ||
                    "No se pudo guardar tu respuesta en el servidor."
            );
        } finally {
            setSaving(false);
        }
    };

    /* ============================================================
     * NAVEGACIÓN ENTRE PREGUNTAS
     * ============================================================ */
    const handleNext = () => {
        if (currentQuestion < questions.length - 1) {
            setCurrentQuestion((prev) => prev + 1);
            window.scrollTo({ top: 0, behavior: "smooth" });
        }
    };

    const handlePrevious = () => {
        if (currentQuestion > 0) {
            setCurrentQuestion((prev) => prev - 1);
            window.scrollTo({ top: 0, behavior: "smooth" });
        }
    };

    /* ============================================================
     * FINALIZAR PRÁCTICA
     * ============================================================ */
    const handleFinishPractice = async () => {
        if (!practiceId || finishing) return;

        const confirmFinish = window.confirm(
            "¿Deseas finalizar esta práctica y consultar tus resultados?"
        );
        if (!confirmFinish) return;

        try {
            setFinishing(true);
            setError("");

            await finishExam(practiceId, {
                time_spent_seconds: secondsElapsed,
            });

            navigate(`/practices-result?id=${practiceId}`);
        } catch (err) {
            console.error("Error al finalizar práctica:", err);
            setError(
                err?.response?.data?.message ||
                    err?.message ||
                    "No se pudo procesar la finalización de la práctica."
            );
            setFinishing(false);
        }
    };

    /* ============================================================
     * ESTADOS DE CARGA Y ERROR INICIAL
     * ============================================================ */
    if (loading) {
        return (
            <div className="main__content">
                <section className="question" style={{ textAlign: "center", padding: "60px 20px" }}>
                    <div style={{ fontSize: "36px", color: "#38bdf8", marginBottom: "18px" }}>
                        <i className="fas fa-spinner fa-spin"></i>
                    </div>
                    <h1 className="question__title" style={{ fontSize: "24px" }}>
                        Cargando preguntas de práctica...
                    </h1>
                    <p style={{ color: "#94a3b8" }}>
                        Estamos consultando los reactivos del área seleccionada.
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
                    <h1 className="question__title" style={{ fontSize: "24px" }}>
                        No se pudo iniciar la práctica
                    </h1>
                    <p style={{ color: "#94a3b8", marginBottom: "24px" }}>{error}</p>
                    <Link to="/practices">
                        <button type="button" className="footer-btn footer-btn--primary">
                            Volver a Selección de Áreas
                        </button>
                    </Link>
                </section>
            </div>
        );
    }

    const currentOptions = getOptions(question);
    const selectedOptionId = question ? answers[question.id] : null;
    const progressPercentage = questions.length > 0
        ? Math.round(((currentQuestion + 1) / questions.length) * 100)
        : 0;
    const subjectName = question?.subject?.name || "Área ICFES";
    const topicName = question?.topic?.name || "";
    const stimulus = question?.stimulus;

    return (
        <>
            <div className="main__content">
                {/* CABECERA DE LA PRÁCTICA */}
                <section className="quiz-top">
                    <div className="quiz-top__left">
                        <span className="quiz-top__subject">
                            PRÁCTICA · {subjectName.toUpperCase()}
                            {topicName ? ` · ${topicName}` : ""}
                        </span>
                        <h2>
                            Pregunta {currentQuestion + 1} de {questions.length}
                        </h2>
                    </div>

                    <div className="quiz-top__timer">
                        <i className="fa-regular fa-clock"></i>
                        <span>{formatTimer(secondsElapsed)}</span>
                    </div>
                </section>

                {/* BARRA DE PROGRESO */}
                <div className="progress">
                    <div
                        className="progress__fill"
                        style={{ width: `${progressPercentage}%` }}
                    ></div>
                </div>

                {/* MENSAJE DE ERROR TEMPORAL */}
                {error && (
                    <div style={{ background: "rgba(239, 68, 68, 0.1)", border: "1px solid #ef4444", color: "#fca5a5", padding: "10px 16px", borderRadius: "8px", marginBottom: "16px", fontSize: "14px" }}>
                        {error}
                    </div>
                )}

                {/* ============================================================
                 * PANEL DE ESTÍMULO COMPARTIDO (LECTURA / INFOGRAFÍA / TABLA)
                 * ============================================================ */}
                {stimulus && (
                    <section className="stimulus-panel">
                        <div
                            className="stimulus-header"
                            onClick={() => setStimulusCollapsed(!stimulusCollapsed)}
                        >
                            <span className="stimulus-badge">
                                <i className="fas fa-book-open"></i> Texto de Lectura
                            </span>
                            <span className="stimulus-title">
                                {stimulus.title || "Lectura compartida"}
                                {isSameStimulusAsPrevious && (
                                    <small style={{ color: "#94a3b8", marginLeft: "8px" }}>
                                        (Continuación del mismo grupo)
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
                                {stimulusCollapsed ? "Ver texto completo" : "Ocultar texto"}
                                <i className={`fas ${stimulusCollapsed ? "fa-chevron-down" : "fa-chevron-up"}`}></i>
                            </button>
                        </div>

                        {!stimulusCollapsed && (
                            <div className="stimulus-body">
                                {stimulus.instructions && (
                                    <div className="stimulus-instructions">
                                        <i className="fas fa-circle-info"></i>
                                        <span>{stimulus.instructions}</span>
                                    </div>
                                )}

                                {stimulus.media_url && (
                                    <div className="stimulus-media">
                                        <img
                                            src={resolveMediaUrl(stimulus.media_url)}
                                            alt={stimulus.title || "Recurso gráfico del estímulo"}
                                        />
                                        {stimulus.media_caption && (
                                            <small className="stimulus-caption">
                                                {stimulus.media_caption}
                                            </small>
                                        )}
                                    </div>
                                )}

                                {stimulus.content_text && (
                                    <div className="stimulus-text">
                                        {stimulus.content_text}
                                    </div>
                                )}
                            </div>
                        )}
                    </section>
                )}

                {/* CONTENEDOR DE LA PREGUNTA */}
                <section className="question">
                    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "14px" }}>
                        <span style={{ fontSize: "12px", color: "#60a5fa", fontWeight: 600 }}>
                            <i className="fas fa-tag" style={{ marginRight: "6px" }}></i>
                            Dificultad: {question?.difficulty === "hard" ? "Difícil" : question?.difficulty === "easy" ? "Fácil" : "Media"}
                        </span>
                        <span style={{ fontSize: "11px", color: "#94a3b8", background: "rgba(255,255,255,0.05)", padding: "4px 8px", borderRadius: "6px" }}>
                            Sesión Oficial · {questions.length} preguntas
                        </span>
                    </div>

                    <h1 className="question__title">{getQuestionText(question)}</h1>

                    {/* IMAGEN ESPECÍFICA DE LA PREGUNTA */}
                    {question?.question_image_url && (
                        <div className="question-image-box">
                            <img
                                src={resolveMediaUrl(question.question_image_url)}
                                alt="Figura o esquema de la pregunta"
                            />
                        </div>
                    )}

                    {/* OPCIONES DE RESPUESTA */}
                    <div className="options">
                        {currentOptions.map((opt) => {
                            // Usar 'option_letter' oficial de la API
                            const optionLetter = opt.option_letter || "A";
                            const isSelected = selectedOptionId === opt.id;

                            return (
                                <button
                                    key={opt.id}
                                    type="button"
                                    className={`option ${isSelected ? "option--selected" : ""}`}
                                    onClick={() => handleSelectOption(opt)}
                                    disabled={saving || finishing}
                                >
                                    <span className="option__letter">{optionLetter}</span>
                                    <div style={{ textAlign: "left", width: "100%" }}>
                                        <span>{getOptionText(opt)}</span>
                                        {opt.option_image_url && (
                                            <div className="option-image-box">
                                                <img
                                                    src={resolveMediaUrl(opt.option_image_url)}
                                                    alt={`Opción ${optionLetter}`}
                                                />
                                            </div>
                                        )}
                                    </div>
                                </button>
                            );
                        })}
                    </div>

                    {saving && (
                        <p style={{ fontSize: "12px", color: "#60a5fa", marginTop: "10px" }}>
                            <i className="fas fa-spinner fa-spin" style={{ marginRight: "6px" }}></i>
                            Guardando respuesta...
                        </p>
                    )}
                </section>

                {/* PIE DE PÁGINA / NAVEGACIÓN */}
                <section className="quiz-footer" style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginTop: "24px" }}>
                    <button
                        type="button"
                        className="footer-btn footer-btn--ghost"
                        onClick={handlePrevious}
                        disabled={currentQuestion === 0 || saving || finishing}
                    >
                        <i className="fas fa-chevron-left"></i>
                        Anterior
                    </button>

                    <div style={{ display: "flex", gap: "10px" }}>
                        <button
                            type="button"
                            className="footer-btn footer-btn--ghost"
                            onClick={handleFinishPractice}
                            disabled={finishing}
                            style={{ borderColor: "#ef4444", color: "#fca5a5" }}
                        >
                            <i className="fas fa-flag-checkered" style={{ marginRight: "6px" }}></i>
                            Finalizar Práctica
                        </button>

                        {currentQuestion < questions.length - 1 ? (
                            <button
                                type="button"
                                className="footer-btn footer-btn--primary"
                                onClick={handleNext}
                                disabled={saving || finishing}
                            >
                                Siguiente
                                <i className="fas fa-chevron-right"></i>
                            </button>
                        ) : (
                            <button
                                type="button"
                                className="footer-btn footer-btn--primary"
                                onClick={handleFinishPractice}
                                disabled={finishing}
                                style={{ background: "#10b981" }}
                            >
                                {finishing ? "Finalizando..." : "Ver Resultados"}
                                <i className="fas fa-check" style={{ marginLeft: "6px" }}></i>
                            </button>
                        )}
                    </div>
                </section>
            </div>

            {/* ASISTENTE ZORRITO */}
            <div className="assistant">
                <div className="assistant__message">
                    ¡Excelente ritmo de práctica! Resolver preguntas activamente consolida tus competencias.
                </div>
                <div className="assistant__bot">
                    <img src={Zorrito} alt="Zorrito" />
                </div>
            </div>
        </>
    );
};

export default PracticeFull;
