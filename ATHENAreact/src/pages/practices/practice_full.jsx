import "./practice_full.css";
import { useEffect, useState } from "react";
import { useSearchParams, useNavigate, Link } from "react-router-dom";
import { useShallow } from "zustand/react/shallow";
import Zorrito from "../../assets/images/Zorrito2D.png";
import { getExamQuestions, submitAnswer, finishExam } from "../../api/exams";
import useExamStore from "../../stores/useExamStore";
import PracticeHeader from "./components/PracticeHeader";
import PracticeOptions from "./components/PracticeOptions";
import PracticeFooter from "./components/PracticeFooter";

const PracticeFull = () => {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();

    // ID del intento de práctica generado en practice_config
    const practiceId = searchParams.get("id");

    const [questions, setQuestions] = useState([]);

    // Selectores optimizados de Zustand:
    // Selector atómico para el índice de la pregunta activa
    const currentIndex = useExamStore((state) => state.currentIndex);
    // useShallow para las acciones de inicialización y reinicio del examen
    const { initPractice, resetPractice } = useExamStore(
        useShallow((state) => ({
            initPractice: state.initPractice,
            resetPractice: state.resetPractice,
        }))
    );

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
                resetPractice();

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

                // Inicializar respuestas previas en Zustand si el intento ya tenía progreso registrado
                const preselected = {};
                receivedQuestions.forEach((q) => {
                    if (q.selected_option_id) {
                        preselected[q.id] = q.selected_option_id;
                    }
                });
                initPractice(preselected, 0);
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
    }, [practiceId, initPractice, resetPractice]);

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
    const question = questions[currentIndex];
    const prevQuestion = currentIndex > 0 ? questions[currentIndex - 1] : null;

    const isSameStimulusAsPrevious = Boolean(
        question?.stimulus &&
        prevQuestion?.stimulus &&
        question.stimulus.id === prevQuestion.stimulus.id
    );

    // Al cambiar de pregunta, colapsar automáticamente solo si es continuación del mismo texto
    const [prevIndex, setPrevIndex] = useState(currentIndex);
    if (prevIndex !== currentIndex) {
        setPrevIndex(currentIndex);
        setStimulusCollapsed(Boolean(isSameStimulusAsPrevious));
    }

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

    const resolveMediaUrl = (url) => {
        if (!url) return null;
        if (url.startsWith("http://") || url.startsWith("https://") || url.startsWith("data:")) {
            return url;
        }
        const cleaned = url.replace(/^\/?storage\//, "");
        return `/storage/${cleaned}`;
    };

    /* ============================================================
     * SINCRONIZACIÓN DE RESPUESTAS CON EL BACKEND
     * ============================================================ */
    const handleOptionSelected = async (option) => {
        if (!question || saving || finishing) return;

        const questionId = question.id;
        const optionId = option.id;

        if (questionId == null || optionId == null) return;

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

            resetPractice();
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
                    <Link to="/practices" onClick={() => resetPractice()}>
                        <button type="button" className="footer-btn footer-btn--primary">
                            Volver a Selección de Áreas
                        </button>
                    </Link>
                </section>
            </div>
        );
    }

    const currentOptions = getOptions(question);
    const subjectName = question?.subject?.name || "Área ICFES";
    const topicName = question?.topic?.name || "";
    const stimulus = question?.stimulus;

    return (
        <>
            <div className="main__content">
                {/* CABECERA Y BARRA DE PROGRESO (Consume useExamStore directamente) */}
                <PracticeHeader
                    subjectName={subjectName}
                    topicName={topicName}
                    totalQuestions={questions.length}
                    secondsElapsed={secondsElapsed}
                    formatTimer={formatTimer}
                />

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

                    {/* OPCIONES DE RESPUESTA (Consume answers y setAnswer directamente de useExamStore) */}
                    <PracticeOptions
                        question={question}
                        options={currentOptions}
                        disabled={saving || finishing}
                        resolveMediaUrl={resolveMediaUrl}
                        onOptionSelected={handleOptionSelected}
                    />

                    {saving && (
                        <p style={{ fontSize: "12px", color: "#60a5fa", marginTop: "10px" }}>
                            <i className="fas fa-spinner fa-spin" style={{ marginRight: "6px" }}></i>
                            Guardando respuesta...
                        </p>
                    )}
                </section>

                {/* PIE DE PÁGINA / NAVEGACIÓN (Consume currentIndex y setCurrentIndex con useShallow) */}
                <PracticeFooter
                    totalQuestions={questions.length}
                    saving={saving}
                    finishing={finishing}
                    onFinish={handleFinishPractice}
                />
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
