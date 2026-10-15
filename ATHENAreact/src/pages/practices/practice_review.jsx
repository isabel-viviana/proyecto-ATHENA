import "./practice_review.css";
import { useEffect, useState, useMemo } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { getExamResults } from "../../api/exams";

const PracticeReview = () => {
    const [searchParams] = useSearchParams();
    const practiceId = searchParams.get("id");

    const [practiceData, setPracticeData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [activeFilter, setActiveFilter] = useState("all");
    const [expandedExplanations, setExpandedExplanations] = useState({});
    const [expandedStimuli, setExpandedStimuli] = useState({});

    useEffect(() => {
        const fetchReview = async () => {
            if (!practiceId) {
                setError("No encontramos el identificador de la práctica a revisar.");
                setLoading(false);
                return;
            }

            try {
                setLoading(true);
                setError("");

                const response = await getExamResults(practiceId);

                const data = response?.data ?? response;
                setPracticeData(data);
            } catch (err) {
                console.error("Error al cargar revisión de práctica:", err);
                setError(
                    err?.response?.data?.message ||
                        err?.message ||
                        "No se pudo cargar la revisión detallada."
                );
            } finally {
                setLoading(false);
            }
        };

        fetchReview();
    }, [practiceId]);

    const toggleExplanation = (qId) => {
        setExpandedExplanations((prev) => ({
            ...prev,
            [qId]: !prev[qId],
        }));
    };

    const toggleStimulus = (stimulusId) => {
        setExpandedStimuli((prev) => ({
            ...prev,
            [stimulusId]: !prev[stimulusId],
        }));
    };

    const resolveMediaUrl = (url) => {
        if (!url) return null;
        if (url.startsWith("http://") || url.startsWith("https://") || url.startsWith("data:")) {
            return url;
        }
        const cleaned = url.replace(/^\/?storage\//, "");
        return `/storage/${cleaned}`;
    };

    const questions = practiceData?.questions_detail ?? [];
    const correctCount =
        practiceData?.correct_answers ??
        questions.filter((q) => q.is_correct && q.user_selected_option !== null).length;
    const incorrectCount =
        practiceData?.incorrect_answers ??
        questions.filter((q) => !q.is_correct && q.user_selected_option !== null).length;
    const omittedCount =
        practiceData?.omitted_answers ??
        questions.filter((q) => q.user_selected_option === null).length;

    const totalCount = practiceData?.total_questions ?? questions.length;
    const accuracy =
        practiceData?.score_percentage ??
        (totalCount > 0 ? Math.round((correctCount / totalCount) * 100) : 0);

    const filteredQuestions = useMemo(() => {
        if (activeFilter === "correct") {
            return questions.filter((q) => q.is_correct && q.user_selected_option !== null);
        }
        if (activeFilter === "wrong") {
            return questions.filter((q) => !q.is_correct && q.user_selected_option !== null);
        }
        if (activeFilter === "omitted") {
            return questions.filter((q) => q.user_selected_option === null);
        }
        return questions;
    }, [questions, activeFilter]);

    if (loading) {
        return (
            <div className="main">
                <section className="page-header">
                    <div className="page-header__content">
                        <h1>Cargando revisión de práctica...</h1>
                        <p>Estamos consultando el desglose de preguntas, reactivos y explicaciones.</p>
                    </div>
                </section>
            </div>
        );
    }

    if (error) {
        return (
            <div className="main">
                <section className="page-header">
                    <div className="page-header__content">
                        <h1>No se pudo cargar la revisión</h1>
                        <p>{error}</p>
                        <div style={{ marginTop: "16px" }}>
                            <Link to="/practices">
                                <button className="btn-outline">Volver a Prácticas</button>
                            </Link>
                        </div>
                    </div>
                </section>
            </div>
        );
    }

    return (
        <div className="main">
            {/* HEADER */}
            <section className="page-header">
                <div className="page-header__content">
                    <Link to={practiceId ? `/practices-result?id=${practiceId}` : "/practices"}>
                        <span className="page-header__breadcrumb" style={{ cursor: "pointer" }}>
                            <i className="fas fa-arrow-left"></i> Volver a resultados
                        </span>
                    </Link>

                    <h1>Revisión de Respuestas</h1>
                    <p>
                        Analiza cada reactivo resuelto, contrasta tu opción con la clave oficial y repasa la explicación pedagógica.
                    </p>
                </div>

                <div className="page-header__stats">
                    <div className="header-mini-card">
                        <span>Aciertos</span>
                        <strong>{correctCount}</strong>
                    </div>

                    <div className="header-mini-card header-mini-card--danger">
                        <span>Errores</span>
                        <strong>{incorrectCount}</strong>
                    </div>

                    <div className="header-mini-card header-mini-card--warning">
                        <span>Omitidas</span>
                        <strong>{omittedCount}</strong>
                    </div>

                    <div className="header-mini-card">
                        <span>Precisión</span>
                        <strong>{accuracy}%</strong>
                    </div>
                </div>
            </section>

            {/* 4 FILTROS OFICIALES: TODAS, CORRECTAS, INCORRECTAS Y OMITIDAS */}
            <section className="filters">
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
                    Correctas ({correctCount})
                </button>

                <button
                    type="button"
                    className={`filter-btn ${activeFilter === "wrong" ? "filter-btn--active" : ""}`}
                    onClick={() => setActiveFilter("wrong")}
                >
                    Incorrectas ({incorrectCount})
                </button>

                <button
                    type="button"
                    className={`filter-btn ${activeFilter === "omitted" ? "filter-btn--active" : ""}`}
                    onClick={() => setActiveFilter("omitted")}
                >
                    Omitidas ({omittedCount})
                </button>
            </section>

            {/* LISTA DE PREGUNTAS DETALLADAS */}
            <section className="questions">
                {filteredQuestions.length === 0 ? (
                    <div style={{ textAlign: "center", padding: "40px", color: "#94a3b8" }}>
                        No hay preguntas en esta categoría de filtro.
                    </div>
                ) : (
                    filteredQuestions.map((q, idx) => {
                        const isOmitted = q.user_selected_option === null;
                        const isCorrect = !isOmitted && q.is_correct;
                        const isWrong = !isOmitted && !q.is_correct;
                        const isExpanded = expandedExplanations[q.question_id];
                        const stimulus = q.stimulus;
                        const stimulusId = stimulus?.id;
                        const isStimulusOpen = stimulusId ? expandedStimuli[stimulusId] !== false : false;

                        // Determinar clase de estado
                        const cardStateClass = isCorrect
                            ? "question-card--correct"
                            : isWrong
                            ? "question-card--wrong"
                            : "question-card--omitted";

                        const iconStateClass = isCorrect
                            ? "question-icon--correct"
                            : isWrong
                            ? "question-icon--wrong"
                            : "question-icon--omitted";

                        const iconSymbol = isCorrect
                            ? "fa-check"
                            : isWrong
                            ? "fa-xmark"
                            : "fa-minus";

                        return (
                            <article
                                key={q.question_id || idx}
                                className={`question-card ${cardStateClass}`}
                            >
                                <div className="question-card__left" style={{ width: "100%" }}>
                                    <div className={`question-icon ${iconStateClass}`}>
                                        <i className={`fas ${iconSymbol}`}></i>
                                    </div>

                                    <div className="question-info" style={{ width: "100%" }}>
                                        <div className="question-top">
                                            <span className="question-category">
                                                {q.subject_name?.toUpperCase() || "ÁREA"} · PREGUNTA {q.order_position || idx + 1}
                                                {q.topic_name ? ` · ${q.topic_name}` : ""}
                                            </span>

                                            <span
                                                className={`question-difficulty ${
                                                    q.difficulty === "hard"
                                                        ? "difficulty--hard"
                                                        : q.difficulty === "easy"
                                                        ? "difficulty--easy"
                                                        : "difficulty--medium"
                                                }`}
                                            >
                                                {q.difficulty === "hard" ? "Difícil" : q.difficulty === "easy" ? "Fácil" : "Media"}
                                            </span>

                                            {isOmitted && (
                                                <span style={{ fontSize: "11px", background: "rgba(234, 179, 8, 0.15)", color: "#facc15", padding: "3px 8px", borderRadius: "6px", fontWeight: 700 }}>
                                                    OMITIDA
                                                </span>
                                            )}
                                        </div>

                                        {/* ESTÍMULO COMPARTIDO ASOCIADO */}
                                        {stimulus && (
                                            <div className="review-stimulus-box">
                                                <div
                                                    className="review-stimulus-header"
                                                    onClick={() => toggleStimulus(stimulusId)}
                                                >
                                                    <span className="review-stimulus-badge">
                                                        <i className="fas fa-book-open" style={{ marginRight: "4px" }}></i>
                                                        Estímulo: {stimulus.title || "Lectura Asociada"}
                                                    </span>
                                                    <small style={{ color: "#38bdf8", cursor: "pointer" }}>
                                                        {isStimulusOpen ? "Ocultar texto" : "Ver texto completo"}
                                                    </small>
                                                </div>

                                                {isStimulusOpen && (
                                                    <div style={{ marginTop: "10px", color: "#94a3b8" }}>
                                                        {stimulus.instructions && (
                                                            <p style={{ fontStyle: "italic", marginBottom: "8px", color: "#cbd5e1" }}>
                                                                {stimulus.instructions}
                                                            </p>
                                                        )}
                                                        {stimulus.media_url && (
                                                            <div className="review-image-box">
                                                                <img
                                                                    src={resolveMediaUrl(stimulus.media_url)}
                                                                    alt={stimulus.title}
                                                                />
                                                            </div>
                                                        )}
                                                        {stimulus.content_text && (
                                                            <p style={{ whiteSpace: "pre-line", maxHeight: "200px", overflowY: "auto", fontSize: "13px" }}>
                                                                {stimulus.content_text}
                                                            </p>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        )}

                                        <h3 style={{ fontSize: "16px", lineHeight: "1.5", margin: "10px 0" }}>
                                            {q.statement}
                                        </h3>

                                        {/* IMAGEN DE LA PREGUNTA SI APLICA */}
                                        {q.question_image_url && (
                                            <div className="review-image-box">
                                                <img
                                                    src={resolveMediaUrl(q.question_image_url)}
                                                    alt="Figura del reactivo"
                                                />
                                            </div>
                                        )}

                                        {/* COMPARACIÓN DE OPCIONES */}
                                        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(260px, 1fr))", gap: "12px", margin: "14px 0" }}>
                                            {/* RESPUESTA DEL ESTUDIANTE */}
                                            <div
                                                style={{
                                                    background: isCorrect
                                                        ? "rgba(16, 185, 129, 0.1)"
                                                        : isWrong
                                                        ? "rgba(239, 68, 68, 0.1)"
                                                        : "rgba(234, 179, 8, 0.1)",
                                                    border: `1px solid ${
                                                        isCorrect
                                                            ? "rgba(16, 185, 129, 0.3)"
                                                            : isWrong
                                                            ? "rgba(239, 68, 68, 0.3)"
                                                            : "rgba(234, 179, 8, 0.3)"
                                                    }`,
                                                    borderRadius: "10px",
                                                    padding: "10px 14px",
                                                }}
                                            >
                                                <small style={{ color: "#94a3b8", display: "block", marginBottom: "2px" }}>
                                                    Tu respuesta:
                                                </small>
                                                <strong
                                                    style={{
                                                        color: isCorrect
                                                            ? "#10b981"
                                                            : isWrong
                                                            ? "#ef4444"
                                                            : "#facc15",
                                                    }}
                                                >
                                                    {q.user_selected_option
                                                        ? `${q.user_selected_option.option_letter}. ${q.user_selected_option.option_text}`
                                                        : "No respondida (Omitida)"}
                                                </strong>
                                                {q.user_selected_option?.option_image_url && (
                                                    <div className="review-image-box">
                                                        <img
                                                            src={resolveMediaUrl(q.user_selected_option.option_image_url)}
                                                            alt="Opción seleccionada"
                                                        />
                                                    </div>
                                                )}
                                            </div>

                                            {/* RESPUESTA CORRECTA OFICIAL SEGÚN SNAPSHOT */}
                                            <div
                                                style={{
                                                    background: "rgba(16, 185, 129, 0.1)",
                                                    border: "1px solid rgba(16, 185, 129, 0.3)",
                                                    borderRadius: "10px",
                                                    padding: "10px 14px",
                                                }}
                                            >
                                                <small style={{ color: "#94a3b8", display: "block", marginBottom: "2px" }}>
                                                    Respuesta correcta oficial:
                                                </small>
                                                <strong style={{ color: "#10b981" }}>
                                                    {q.correct_option
                                                        ? `${q.correct_option.option_letter}. ${q.correct_option.option_text}`
                                                        : "Clave oficial registrada"}
                                                </strong>
                                                {q.correct_option?.option_image_url && (
                                                    <div className="review-image-box">
                                                        <img
                                                            src={resolveMediaUrl(q.correct_option.option_image_url)}
                                                            alt="Opción correcta"
                                                        />
                                                    </div>
                                                )}
                                            </div>
                                        </div>

                                        {/* EXPLICACIÓN PEDAGÓGICA (ÚNICAMENTE SI ESTÁ PRESENTE) */}
                                        {q.explanation && (
                                            <>
                                                {isExpanded && (
                                                    <div
                                                        style={{
                                                            background: "#16233b",
                                                            border: "1px solid #1e293b",
                                                            borderRadius: "10px",
                                                            padding: "14px",
                                                            marginTop: "12px",
                                                            fontSize: "14px",
                                                            lineHeight: "1.6",
                                                            color: "#cbd5e1",
                                                        }}
                                                    >
                                                        <strong style={{ color: "#60a5fa", display: "block", marginBottom: "6px" }}>
                                                            <i className="fas fa-lightbulb" style={{ marginRight: "6px" }}></i>
                                                            Justificación Académica:
                                                        </strong>
                                                        {q.explanation}
                                                    </div>
                                                )}

                                                <div style={{ marginTop: "12px" }}>
                                                    <button
                                                        type="button"
                                                        className="btn-outline"
                                                        onClick={() => toggleExplanation(q.question_id)}
                                                        style={{ padding: "6px 14px", fontSize: "13px" }}
                                                    >
                                                        <i
                                                            className={`fas ${isExpanded ? "fa-chevron-up" : "fa-chevron-down"}`}
                                                            style={{ marginRight: "6px" }}
                                                        ></i>
                                                        {isExpanded ? "Ocultar Explicación" : "Ver Explicación"}
                                                    </button>
                                                </div>
                                            </>
                                        )}
                                    </div>
                                </div>
                            </article>
                        );
                    })
                )}
            </section>

            {/* ACCIONES AL FINAL */}
            <div style={{ display: "flex", justifyContent: "center", gap: "14px", marginTop: "40px", marginBottom: "60px" }}>
                <Link to={practiceId ? `/practices-result?id=${practiceId}` : "/practices"}>
                    <button className="btn-outline">
                        <i className="fas fa-arrow-left" style={{ marginRight: "8px" }}></i>
                        Volver a Resultados
                    </button>
                </Link>

                <Link to="/practices">
                    <button className="btn-primary" style={{ background: "#3b82f6", color: "white", padding: "12px 24px", borderRadius: "10px", border: "none", cursor: "pointer", fontWeight: 600 }}>
                        <i className="fas fa-arrow-rotate-right" style={{ marginRight: "8px" }}></i>
                        Nueva Práctica por Área
                    </button>
                </Link>
            </div>
        </div>
    );
};

export default PracticeReview;
