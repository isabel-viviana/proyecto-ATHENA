import "./mock_review.css";
import { useEffect, useState, useMemo } from "react";
import { Link, useSearchParams, useNavigate } from "react-router-dom";
import { getExamResults } from "../../api/exams";
import { getStudentMediaUrl } from "../../api/studentQuestions";
import PracticeLightbox from "../practices/components/PracticeLightbox";

const formatTime = (seconds) => {
    if (!seconds || seconds <= 0) return "0 seg";
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    if (mins === 0) return `${secs}s`;
    return `${mins}m ${secs}s`;
};

const MockReview = () => {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const examId = searchParams.get("id");

    const [examData, setExamData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [activeFilter, setActiveFilter] = useState("all");
    const [expandedQuestions, setExpandedQuestions] = useState({});

    // Lightbox modal para zoom de caricaturas e infografías
    const [lightboxState, setLightboxState] = useState({
        isOpen: false,
        src: "",
        alt: "",
        caption: "",
    });

    useEffect(() => {
        const fetchReviewData = async () => {
            if (!examId) {
                setError("No encontramos el ID del simulacro para revisar. Regresa a tus resultados o al historial.");
                setLoading(false);
                return;
            }

            try {
                setLoading(true);
                setError("");
                const raw = await getExamResults(examId);
                const data = raw?.data ?? raw;
                setExamData(data);

                // Expandir por defecto todas las preguntas para lectura fluida
                if (Array.isArray(data?.questions_detail)) {
                    const expandedMap = {};
                    data.questions_detail.forEach((q) => {
                        expandedMap[q.question_id] = true;
                    });
                    setExpandedQuestions(expandedMap);
                }
            } catch (err) {
                console.error("ERROR AL CARGAR REVISIÓN:", err);
                setError(
                    err?.response?.data?.message ??
                        err?.message ??
                        "No se pudo cargar el detalle de respuestas del simulacro."
                );
            } finally {
                setLoading(false);
            }
        };

        fetchReviewData();
    }, [examId]);

    const toggleExplanation = (id) => {
        setExpandedQuestions((prev) => ({
            ...prev,
            [id]: !prev[id],
        }));
    };

    const openLightbox = (src, alt, caption) => {
        setLightboxState({
            isOpen: true,
            src,
            alt: alt || "Recurso gráfico",
            caption: caption || "",
        });
    };

    const closeLightbox = () => {
        setLightboxState((prev) => ({ ...prev, isOpen: false }));
    };

    const attempt = examData?.attempt ?? {};
    const questions = examData?.questions_detail ?? [];

    const correctCount = attempt?.correct_answers ?? questions.filter((q) => q.is_correct).length;
    const incorrectCount = attempt?.incorrect_answers ?? questions.filter((q) => !q.is_correct && !q.is_omitted).length;
    const omittedCount = attempt?.omitted_answers ?? questions.filter((q) => q.is_omitted).length;
    const flaggedCount = questions.filter((q) => q.flagged).length;
    const score = attempt?.score ?? 0;
    const accuracy = attempt?.accuracy ?? (questions.length > 0 ? Math.round((correctCount / questions.length) * 100) : 0);

    const filteredQuestions = useMemo(() => {
        if (activeFilter === "all") return questions;
        if (activeFilter === "correct") return questions.filter((q) => q.is_correct);
        if (activeFilter === "wrong") return questions.filter((q) => !q.is_correct && !q.is_omitted);
        if (activeFilter === "omitted") return questions.filter((q) => q.is_omitted);
        if (activeFilter === "flagged") return questions.filter((q) => q.flagged);
        return questions;
    }, [questions, activeFilter]);

    if (loading) {
        return (
            <div className="main" style={{ maxWidth: "1000px", margin: "0 auto", padding: "40px 24px" }}>
                <section className="page-header" style={{ textAlign: "center", padding: "60px 20px" }}>
                    <div style={{ fontSize: "36px", color: "#38bdf8", marginBottom: "16px" }}>
                        <i className="fas fa-spinner fa-spin"></i>
                    </div>
                    <h1>Cargando revisión del simulacro...</h1>
                    <p style={{ color: "#94a3b8" }}>
                        Recuperando claves oficiales, justificaciones pedagógicas y reactivos multimedia.
                    </p>
                </section>
            </div>
        );
    }

    if (error) {
        return (
            <div className="main" style={{ maxWidth: "1000px", margin: "0 auto", padding: "40px 24px" }}>
                <section className="page-header" style={{ textAlign: "center", padding: "60px 20px" }}>
                    <div style={{ fontSize: "36px", color: "#ef4444", marginBottom: "16px" }}>
                        <i className="fas fa-triangle-exclamation"></i>
                    </div>
                    <h1>No pudimos cargar la revisión</h1>
                    <p style={{ color: "#94a3b8", marginBottom: "24px" }}>{error}</p>
                    <button
                        type="button"
                        className="btn-outline"
                        onClick={() => navigate("/mock/history")}
                    >
                        Volver a Mi Historial
                    </button>
                </section>
            </div>
        );
    }

    return (
        <div className="main" style={{ maxWidth: "1000px", margin: "0 auto", padding: "30px 24px" }}>

            {/* ENCABEZADO Y RESUMEN DEL INTENTO */}
            <section className="page-header">
                <div className="page-header__content">
                    <span style={{
                        display: "inline-block",
                        backgroundColor: "rgba(56, 189, 248, 0.15)",
                        border: "1px solid rgba(56, 189, 248, 0.35)",
                        color: "#38bdf8",
                        padding: "4px 12px",
                        borderRadius: "999px",
                        fontSize: "12px",
                        fontWeight: 700,
                        letterSpacing: "0.5px",
                        marginBottom: "10px",
                    }}>
                        REVISIÓN PEDAGÓGICA DETALLADA
                    </span>
                    <h1>{attempt.title || "Simulacro ICFES"}</h1>
                    <p style={{ color: "#94a3b8" }}>
                        Consulta tu respuesta, la opción correcta oficial y la justificación explicativa para cada reactivo.
                    </p>
                </div>

                <div className="review-summary-pills">
                    <div className="review-pill">
                        <span>Puntaje</span>
                        <strong>{score}</strong>
                    </div>
                    <div className="review-pill review-pill--correct">
                        <span>Correctas</span>
                        <strong>{correctCount}</strong>
                    </div>
                    <div className="review-pill review-pill--wrong">
                        <span>Incorrectas</span>
                        <strong>{incorrectCount}</strong>
                    </div>
                    {omittedCount > 0 && (
                        <div className="review-pill review-pill--omitted">
                            <span>Omitidas</span>
                            <strong>{omittedCount}</strong>
                        </div>
                    )}
                    <div className="review-pill">
                        <span>Precisión</span>
                        <strong>{accuracy}%</strong>
                    </div>
                </div>
            </section>

            {/* BARRA DE FILTROS */}
            <section className="filters" style={{ display: "flex", gap: "8px", flexWrap: "wrap", margin: "24px 0" }}>
                <button
                    type="button"
                    className={`filter-btn ${activeFilter === "all" ? "filter-btn--active" : ""}`}
                    onClick={() => setActiveFilter("all")}
                >
                    Todas ({questions.length})
                </button>

                <button
                    type="button"
                    className={`filter-btn ${activeFilter === "correct" ? "filter-btn--active" : ""}`}
                    onClick={() => setActiveFilter("correct")}
                >
                    <i className="fas fa-check" style={{ color: "#22c55e", marginRight: "6px" }}></i>
                    Correctas ({correctCount})
                </button>

                <button
                    type="button"
                    className={`filter-btn ${activeFilter === "wrong" ? "filter-btn--active" : ""}`}
                    onClick={() => setActiveFilter("wrong")}
                >
                    <i className="fas fa-xmark" style={{ color: "#ef4444", marginRight: "6px" }}></i>
                    Incorrectas ({incorrectCount})
                </button>

                {omittedCount > 0 && (
                    <button
                        type="button"
                        className={`filter-btn ${activeFilter === "omitted" ? "filter-btn--active" : ""}`}
                        onClick={() => setActiveFilter("omitted")}
                    >
                        <i className="fas fa-circle-question" style={{ color: "#94a3b8", marginRight: "6px" }}></i>
                        Omitidas ({omittedCount})
                    </button>
                )}

                {flaggedCount > 0 && (
                    <button
                        type="button"
                        className={`filter-btn ${activeFilter === "flagged" ? "filter-btn--active" : ""}`}
                        onClick={() => setActiveFilter("flagged")}
                    >
                        <i className="fas fa-flag" style={{ color: "#f59e0b", marginRight: "6px" }}></i>
                        Marcadas ({flaggedCount})
                    </button>
                )}
            </section>

            {/* LISTADO DE PREGUNTAS CON ESTÍMULO Y EXPLICACIÓN */}
            <section className="questions" style={{ display: "flex", flexDirection: "column", gap: "24px" }}>
                {filteredQuestions.length === 0 ? (
                    <article className="question-card" style={{ textAlign: "center", padding: "40px" }}>
                        <p style={{ color: "var(--text-secondary)" }}>
                            No hay preguntas que coincidan con el filtro seleccionado.
                        </p>
                    </article>
                ) : (
                    filteredQuestions.map((q, index) => {
                        const qId = q.question_id || index;
                        const isOmitted = q.is_omitted;
                        const isCorrect = q.is_correct && !isOmitted;
                        const isExpanded = !!expandedQuestions[qId];

                        const stimulus = q.stimulus;
                        let stimulusMediaPath = null;
                        if (stimulus && Array.isArray(stimulus.confirmed_media_assets) && stimulus.confirmed_media_assets.length > 0) {
                            stimulusMediaPath = stimulus.confirmed_media_assets[0]?.file_path;
                        }

                        let questionMediaPath = null;
                        if (Array.isArray(q.confirmed_media_assets) && q.confirmed_media_assets.length > 0) {
                            questionMediaPath = q.confirmed_media_assets[0]?.file_path;
                        }

                        const cardClass = isCorrect
                            ? "question-card--correct"
                            : isOmitted
                            ? "question-card--omitted"
                            : "question-card--wrong";

                        const iconClass = isCorrect
                            ? "question-icon--correct"
                            : isOmitted
                            ? "question-icon--omitted"
                            : "question-icon--wrong";

                        const iconGraphic = isCorrect ? (
                            <i className="fas fa-check"></i>
                        ) : isOmitted ? (
                            <i className="fas fa-minus"></i>
                        ) : (
                            <i className="fas fa-xmark"></i>
                        );

                        return (
                            <article className={`question-card ${cardClass}`} key={qId} style={{ borderRadius: "16px", padding: "24px" }}>
                                <div className="question-card__main-row" style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", gap: "16px" }}>
                                    <div className="question-card__left" style={{ display: "flex", gap: "16px", flex: 1 }}>
                                        <div className={`question-icon ${iconClass}`}>
                                            {iconGraphic}
                                        </div>

                                        <div className="question-info" style={{ flex: 1 }}>
                                            <div className="question-top" style={{ display: "flex", gap: "10px", alignItems: "center", flexWrap: "wrap", marginBottom: "8px" }}>
                                                <span className="question-category" style={{ fontSize: "12px", fontWeight: 700, color: "#38bdf8" }}>
                                                    {q.subject_name ? q.subject_name.toUpperCase() : "LECTURA CRÍTICA"} · PREGUNTA {q.order || index + 1}
                                                </span>

                                                {q.flagged && (
                                                    <span style={{
                                                        backgroundColor: "rgba(245, 158, 11, 0.2)",
                                                        color: "#fbbf24",
                                                        fontSize: "11px",
                                                        fontWeight: 700,
                                                        padding: "2px 8px",
                                                        borderRadius: "999px",
                                                        border: "1px solid rgba(245, 158, 11, 0.4)",
                                                    }}>
                                                        <i className="fas fa-flag" style={{ marginRight: "4px" }}></i> Marcada
                                                    </span>
                                                )}
                                            </div>

                                            <h3 style={{ fontSize: "16px", fontWeight: 600, lineHeight: "1.5", margin: "6px 0 12px" }}>
                                                {q.statement}
                                            </h3>

                                            <div className="question-meta" style={{ display: "flex", gap: "16px", fontSize: "13px", color: "#94a3b8" }}>
                                                <span>
                                                    <i className="fas fa-clock" style={{ marginRight: "6px" }}></i>
                                                    {formatTime(q.time_spent_seconds)}
                                                </span>

                                                <span style={isCorrect ? { color: "#4ade80" } : isOmitted ? { color: "#94a3b8" } : { color: "#f87171" }}>
                                                    <i
                                                        className={
                                                            isCorrect
                                                                ? "fas fa-check-circle"
                                                                : isOmitted
                                                                ? "fas fa-circle-question"
                                                                : "fas fa-circle-xmark"
                                                        }
                                                        style={{ marginRight: "6px" }}
                                                    ></i>
                                                    {isCorrect
                                                        ? "Respuesta acertada"
                                                        : isOmitted
                                                        ? "Pregunta omitida"
                                                        : "Respuesta incorrecta"}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="question-actions">
                                        <button
                                            type="button"
                                            className="btn-outline"
                                            onClick={() => toggleExplanation(qId)}
                                            style={{ whiteSpace: "nowrap" }}
                                        >
                                            {isExpanded ? "Ocultar Detalle" : "Ver Detalle"}
                                        </button>
                                    </div>
                                </div>

                                {/* SECCIÓN EXPANDIBLE: ESTÍMULO, OPCIONES Y EXPLICACIÓN OFICIAL */}
                                {isExpanded && (
                                    <div className="question-card__expanded" style={{ marginTop: "20px", borderTop: "1px solid rgba(148, 163, 184, 0.15)", paddingTop: "18px" }}>

                                        {/* ESTÍMULO MULTIMODAL SI APLICA */}
                                        {stimulus && (
                                            <div style={{
                                                backgroundColor: "rgba(15, 23, 42, 0.6)",
                                                border: "1px solid rgba(148, 163, 184, 0.2)",
                                                borderRadius: "12px",
                                                padding: "16px",
                                                marginBottom: "18px",
                                            }}>
                                                <div style={{ display: "flex", alignItems: "center", gap: "8px", marginBottom: "8px", flexWrap: "wrap" }}>
                                                    <span style={{
                                                        backgroundColor: "rgba(56, 189, 248, 0.15)",
                                                        color: "#38bdf8",
                                                        fontSize: "11px",
                                                        fontWeight: 700,
                                                        padding: "2px 8px",
                                                        borderRadius: "4px",
                                                    }}>
                                                        LECTURA COMPARTIDA
                                                    </span>
                                                    <strong style={{ fontSize: "14px", color: "#f8fafc" }}>
                                                        {stimulus.title}
                                                    </strong>
                                                    {stimulus.source_page && (
                                                        <span style={{ fontSize: "12px", color: "#38bdf8" }}>
                                                            · Pág. {stimulus.source_page}
                                                        </span>
                                                    )}
                                                </div>

                                                {stimulus.instructions && (
                                                    <div style={{ fontSize: "12px", fontStyle: "italic", color: "#94a3b8", marginBottom: "10px" }}>
                                                        {stimulus.instructions}
                                                    </div>
                                                )}

                                                {/* IMAGEN DE ESTÍMULO (HISTORIETA O INFOGRAFÍA) */}
                                                {stimulusMediaPath && (
                                                    <div style={{ margin: "12px 0", textAlign: "center" }}>
                                                        <div
                                                            onClick={() => openLightbox(getStudentMediaUrl(stimulusMediaPath), stimulus.title, `Recurso de la página ${stimulus.source_page || "cuadernillo"}`)}
                                                            style={{
                                                                display: "inline-block",
                                                                cursor: "zoom-in",
                                                                borderRadius: "8px",
                                                                overflow: "hidden",
                                                                background: "#ffffff",
                                                                padding: "4px",
                                                                border: "1px solid rgba(148, 163, 184, 0.3)",
                                                            }}
                                                        >
                                                            <img
                                                                src={getStudentMediaUrl(stimulusMediaPath)}
                                                                alt={stimulus.title}
                                                                style={{ maxWidth: "100%", maxHeight: "280px", objectFit: "contain", display: "block" }}
                                                            />
                                                            <span style={{ display: "block", fontSize: "10px", color: "#475569", marginTop: "2px" }}>
                                                                <i className="fas fa-magnifying-glass-plus"></i> Clic para ampliar
                                                            </span>
                                                        </div>
                                                    </div>
                                                )}

                                                {/* TEXTO DE LECTURA */}
                                                {stimulus.content_text && (
                                                    <div style={{ color: "#cbd5e1", fontSize: "14px", lineHeight: "1.7", whiteSpace: "pre-line" }}>
                                                        {stimulus.content_text}
                                                    </div>
                                                )}
                                            </div>
                                        )}

                                        {/* IMAGEN A NIVEL DE PREGUNTA */}
                                        {questionMediaPath && (
                                            <div style={{ margin: "12px 0", textAlign: "center" }}>
                                                <div
                                                    onClick={() => openLightbox(getStudentMediaUrl(questionMediaPath), "Recurso de pregunta", "Imagen de la pregunta")}
                                                    style={{
                                                        display: "inline-block",
                                                        cursor: "zoom-in",
                                                        borderRadius: "8px",
                                                        overflow: "hidden",
                                                        background: "#ffffff",
                                                        padding: "4px",
                                                        border: "1px solid rgba(148, 163, 184, 0.3)",
                                                    }}
                                                >
                                                    <img
                                                        src={getStudentMediaUrl(questionMediaPath)}
                                                        alt="Recurso de la pregunta"
                                                        style={{ maxWidth: "100%", maxHeight: "240px", objectFit: "contain", display: "block" }}
                                                    />
                                                </div>
                                            </div>
                                        )}

                                        {/* OPCIONES DE RESPUESTA */}
                                        <div className="review-answers-box" style={{ display: "flex", flexDirection: "column", gap: "10px", margin: "16px 0" }}>
                                            {Array.isArray(q.options) && q.options.map((opt) => {
                                                const isUserSelected =
                                                    (q.user_selected_id && opt.id === q.user_selected_id) ||
                                                    (q.user_selected_option && opt.option_letter === q.user_selected_option);

                                                const isOptionCorrect = Boolean(
                                                    opt.is_correct ||
                                                    (q.correct_option_id && opt.id === q.correct_option_id) ||
                                                    (q.correct_option_letter && opt.option_letter === q.correct_option_letter)
                                                );

                                                let border = "1px solid rgba(148, 163, 184, 0.15)";
                                                let bg = "rgba(15, 23, 42, 0.4)";
                                                let tagBg = null;
                                                let tagColor = null;
                                                let tagText = null;

                                                if (isOptionCorrect) {
                                                    border = "1.5px solid #22c55e";
                                                    bg = "rgba(34, 197, 94, 0.12)";
                                                    tagBg = "rgba(34, 197, 94, 0.25)";
                                                    tagColor = "#4ade80";
                                                    tagText = "Respuesta Correcta";
                                                }

                                                if (isUserSelected) {
                                                    if (isOptionCorrect) {
                                                        tagText = "Tu Respuesta (Correcta)";
                                                    } else {
                                                        border = "1.5px solid #ef4444";
                                                        bg = "rgba(239, 68, 68, 0.12)";
                                                        tagBg = "rgba(239, 68, 68, 0.25)";
                                                        tagColor = "#f87171";
                                                        tagText = "Tu Respuesta (Incorrecta)";
                                                    }
                                                }

                                                return (
                                                    <div
                                                        key={opt.id}
                                                        style={{
                                                            padding: "12px 16px",
                                                            borderRadius: "10px",
                                                            border,
                                                            backgroundColor: bg,
                                                            display: "flex",
                                                            alignItems: "center",
                                                            justifyContent: "space-between",
                                                            gap: "12px",
                                                        }}
                                                    >
                                                        <div style={{ display: "flex", alignItems: "center", gap: "10px", flex: 1 }}>
                                                            <span style={{
                                                                width: "28px",
                                                                height: "28px",
                                                                borderRadius: "50%",
                                                                backgroundColor: isOptionCorrect ? "#22c55e" : isUserSelected ? "#ef4444" : "rgba(30, 41, 59, 0.8)",
                                                                color: "#ffffff",
                                                                display: "flex",
                                                                alignItems: "center",
                                                                justifyContent: "center",
                                                                fontSize: "13px",
                                                                fontWeight: 700,
                                                                flexShrink: 0,
                                                            }}>
                                                                {opt.option_letter}
                                                            </span>
                                                            <span style={{ color: "#e2e8f0", fontSize: "14px", lineHeight: "1.5" }}>
                                                                {opt.option_text}
                                                            </span>
                                                        </div>

                                                        {tagText && (
                                                            <span style={{
                                                                backgroundColor: tagBg,
                                                                color: tagColor,
                                                                padding: "4px 10px",
                                                                borderRadius: "6px",
                                                                fontSize: "11px",
                                                                fontWeight: 700,
                                                                whiteSpace: "nowrap",
                                                            }}>
                                                                {tagText}
                                                            </span>
                                                        )}
                                                    </div>
                                                );
                                            })}
                                        </div>

                                        {/* EXPLICACIÓN PEDAGÓGICA OFICIAL */}
                                        <div style={{
                                            backgroundColor: "rgba(30, 41, 59, 0.7)",
                                            borderLeft: "4px solid #38bdf8",
                                            borderRadius: "8px",
                                            padding: "14px 18px",
                                            marginTop: "14px",
                                        }}>
                                            <div style={{ display: "flex", alignItems: "center", gap: "8px", color: "#38bdf8", fontWeight: 700, fontSize: "13px", marginBottom: "6px" }}>
                                                <i className="fas fa-lightbulb"></i>
                                                EXPLICACIÓN PEDAGÓGICA ICFES
                                            </div>
                                            <p style={{ color: "#e2e8f0", fontSize: "14px", lineHeight: "1.65", margin: 0 }}>
                                                {q.explanation}
                                            </p>
                                        </div>
                                    </div>
                                )}
                            </article>
                        );
                    })
                )}
            </section>

            {/* BOTONES INFERIORES */}
            <section style={{ marginTop: "36px", display: "flex", gap: "12px", justifyContent: "center", flexWrap: "wrap" }}>
                <Link to={`/mock/result?id=${examId}`}>
                    <button type="button" className="btn-outline">
                        <i className="fas fa-arrow-left" style={{ marginRight: "8px" }}></i>
                        Volver a Resultados
                    </button>
                </Link>

                <Link to="/mock/history">
                    <button type="button" className="btn-outline">
                        <i className="fas fa-clock-rotate-left" style={{ marginRight: "8px" }}></i>
                        Ver Mi Historial
                    </button>
                </Link>

                <Link to="/mock/config">
                    <button type="button" style={{
                        backgroundColor: "#0284c7",
                        color: "#ffffff",
                        border: "none",
                        borderRadius: "10px",
                        padding: "10px 20px",
                        fontWeight: 700,
                        fontSize: "14px",
                        cursor: "pointer",
                        display: "inline-flex",
                        alignItems: "center",
                        gap: "8px",
                    }}>
                        <i className="fas fa-rotate-right"></i>
                        Presentar Nuevo Simulacro
                    </button>
                </Link>
            </section>

            {/* LIGHTBOX PARA ZOOM */}
            <PracticeLightbox
                isOpen={lightboxState.isOpen}
                onClose={closeLightbox}
                src={lightboxState.src}
                alt={lightboxState.alt}
                caption={lightboxState.caption}
            />
        </div>
    );
};

export default MockReview;
