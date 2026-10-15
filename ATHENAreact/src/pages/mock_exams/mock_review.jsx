import "./mock_review.css";
import { useEffect, useState, useMemo } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { getExamResults } from "../../api/exams";

const formatTime = (seconds) => {
    if (!seconds || seconds <= 0) return "0s";
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    if (mins === 0) return `${secs}s`;
    return `${mins}m ${secs}s`;
};

const formatDifficulty = (difficulty) => {
    switch (difficulty?.toLowerCase()) {
        case "easy":
            return "Fácil";
        case "hard":
            return "Difícil";
        case "medium":
        default:
            return "Media";
    }
};

const MockReview = () => {
    const [searchParams] = useSearchParams();
    const examId = searchParams.get("id");

    const [examData, setExamData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [activeFilter, setActiveFilter] = useState("all");
    const [expandedQuestions, setExpandedQuestions] = useState({});

    /* ----------------------------------------------------------
       CARGA DE RESULTADOS DESDE LA API
    ---------------------------------------------------------- */
    useEffect(() => {
        const fetchReviewData = async () => {
            if (!examId) {
                setError(
                    "No encontramos el ID del simulacro para revisar. Regresa a tus resultados o al dashboard."
                );
                setLoading(false);
                return;
            }

            try {
                setLoading(true);
                setError("");
                const raw = await getExamResults(examId);
                const data = raw?.data ?? raw;
                setExamData(data);
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

    /* ----------------------------------------------------------
       DATOS NORMALIZADOS
    ---------------------------------------------------------- */
    const questions = examData?.questions_detail ?? [];
    const correctCount =
        examData?.correct_answers ?? questions.filter((q) => q.is_correct).length;
    const incorrectCount =
        examData?.incorrect_answers ??
        questions.filter((q) => !q.is_correct && q.user_selected_option !== null).length;
    const omittedCount =
        examData?.omitted_answers ??
        questions.filter((q) => q.user_selected_option === null).length;
    const scorePercentage =
        examData?.score_percentage ??
        (questions.length > 0 ? Math.round((correctCount / questions.length) * 100) : 0);

    const availableSubjects = useMemo(() => {
        const set = new Set();
        questions.forEach((q) => {
            if (q.subject_name) set.add(q.subject_name);
        });
        return Array.from(set);
    }, [questions]);

    const filteredQuestions = useMemo(() => {
        if (activeFilter === "all") return questions;
        if (activeFilter === "correct") {
            return questions.filter(
                (q) => q.is_correct && q.user_selected_option !== null
            );
        }
        if (activeFilter === "wrong") {
            return questions.filter(
                (q) => !q.is_correct && q.user_selected_option !== null
            );
        }
        if (activeFilter === "omitted") {
            return questions.filter((q) => q.user_selected_option === null);
        }
        return questions.filter((q) => q.subject_name === activeFilter);
    }, [questions, activeFilter]);

    /* ----------------------------------------------------------
       ESTADO DE CARGA
    ---------------------------------------------------------- */
    if (loading) {
        return (
            <div className="main">
                <section className="page-header">
                    <div className="page-header__content">
                        <h1>Cargando revisión...</h1>
                        <p>Estamos consultando el detalle de tus respuestas.</p>
                    </div>
                </section>
            </div>
        );
    }

    /* ----------------------------------------------------------
       ESTADO DE ERROR
    ---------------------------------------------------------- */
    if (error) {
        return (
            <div className="main">
                <section className="page-header">
                    <div className="page-header__content">
                        <Link to={examId ? `/mock/result?id=${examId}` : "/mock/result"}>
                            <span className="page-header__breadcrumb">
                                <i className="fas fa-arrow-left"></i>
                                Volver a resultados
                            </span>
                        </Link>
                        <h1>No pudimos cargar la revisión</h1>
                        <p>{error}</p>
                    </div>
                    <div style={{ marginTop: "16px" }}>
                        <Link to="/dashboard">
                            <button className="btn-outline">Volver al Dashboard</button>
                        </Link>
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
                    <Link to={examId ? `/mock/result?id=${examId}` : "/mock/result"}>
                        <span className="page-header__breadcrumb">
                            <i className="fas fa-arrow-left"></i>
                            Volver a resultados
                        </span>
                    </Link>

                    <h1>Detalle de Respuestas</h1>
                    <p>
                        Revisa tu rendimiento pregunta por pregunta y analiza la
                        explicación oficial del ICFES para reforzar tus temas débiles.
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

                    {omittedCount > 0 && (
                        <div className="header-mini-card">
                            <span>Omitidas</span>
                            <strong>{omittedCount}</strong>
                        </div>
                    )}

                    <div className="header-mini-card">
                        <span>Precisión</span>
                        <strong>{scorePercentage}%</strong>
                    </div>
                </div>
            </section>

            {/* FILTROS */}
            <section className="filters">
                <button
                    className={`filter-btn ${activeFilter === "all" ? "filter-btn--active" : ""}`}
                    onClick={() => setActiveFilter("all")}
                >
                    Todas ({questions.length})
                </button>

                <button
                    className={`filter-btn ${activeFilter === "correct" ? "filter-btn--active" : ""}`}
                    onClick={() => setActiveFilter("correct")}
                >
                    Correctas ({correctCount})
                </button>

                <button
                    className={`filter-btn ${activeFilter === "wrong" ? "filter-btn--active" : ""}`}
                    onClick={() => setActiveFilter("wrong")}
                >
                    Incorrectas ({incorrectCount})
                </button>

                {omittedCount > 0 && (
                    <button
                        className={`filter-btn ${activeFilter === "omitted" ? "filter-btn--active" : ""}`}
                        onClick={() => setActiveFilter("omitted")}
                    >
                        Omitidas ({omittedCount})
                    </button>
                )}

                {availableSubjects.map((subj) => (
                    <button
                        key={subj}
                        className={`filter-btn ${activeFilter === subj ? "filter-btn--active" : ""}`}
                        onClick={() => setActiveFilter(subj)}
                    >
                        {subj}
                    </button>
                ))}
            </section>

            {/* LISTA DE PREGUNTAS */}
            <section className="questions">
                {filteredQuestions.length === 0 ? (
                    <article className="question-card">
                        <p style={{ color: "var(--text-secondary)" }}>
                            No hay preguntas que coincidan con el filtro seleccionado.
                        </p>
                    </article>
                ) : (
                    filteredQuestions.map((q, index) => {
                        const qId = q.question_id ?? index;
                        const isOmitted = q.user_selected_option === null;
                        const isCorrect = q.is_correct && !isOmitted;
                        const isExpanded = !!expandedQuestions[qId];

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

                        const difficultyClass = `difficulty--${q.difficulty?.toLowerCase() || "medium"}`;

                        return (
                            <article className={`question-card ${cardClass}`} key={qId}>
                                <div className="question-card__main-row">
                                    <div className="question-card__left">
                                        <div className={`question-icon ${iconClass}`}>
                                            {iconGraphic}
                                        </div>

                                        <div className="question-info">
                                            <div className="question-top">
                                                <span className="question-category">
                                                    {(q.subject_name || "ÁREA").toUpperCase()}
                                                    {q.topic_name ? ` · ${q.topic_name}` : ""} · PREGUNTA {index + 1}
                                                </span>

                                                <span className={`question-difficulty ${difficultyClass}`}>
                                                    {formatDifficulty(q.difficulty)}
                                                </span>
                                            </div>

                                            <h3>{q.statement}</h3>

                                            <div className="question-meta">
                                                <span>
                                                    <i className="fas fa-clock"></i>
                                                    {formatTime(q.time_spent_seconds)}
                                                </span>

                                                <span>
                                                    <i
                                                        className={
                                                            isCorrect
                                                                ? "fas fa-check-circle"
                                                                : isOmitted
                                                                ? "fas fa-circle-question"
                                                                : "fas fa-circle-xmark"
                                                        }
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
                                        >
                                            {isExpanded ? "Ocultar Explicación" : "Ver Explicación"}
                                        </button>
                                    </div>
                                </div>

                                {/* SECCIÓN EXPANDIBLE: OPCIONES Y EXPLICACIÓN */}
                                {isExpanded && (
                                    <div className="question-card__expanded">
                                        {/* 1. OPCIONES (SI VIENEN DISPONIBLES O DETALLE SELECCIONADO / CORRECTO) */}
                                        <div className="review-answers-box">
                                            {Array.isArray(q.options) && q.options.length > 0 ? (
                                                q.options.map((opt) => {
                                                    const isUserSelected =
                                                        q.user_selected_option?.id === opt.id;
                                                    const isOptionCorrect =
                                                        q.correct_option?.id === opt.id;

                                                    let itemClass = "review-answer-item--neutral";
                                                    if (isOptionCorrect) {
                                                        itemClass = "review-answer-item--correct";
                                                    } else if (isUserSelected && !q.is_correct) {
                                                        itemClass = "review-answer-item--wrong";
                                                    }

                                                    return (
                                                        <div
                                                            key={opt.id}
                                                            className={`review-answer-item ${itemClass}`}
                                                        >
                                                            {isOptionCorrect && (
                                                                <span className="review-answer-tag review-answer-tag--correct">
                                                                    Correcta
                                                                </span>
                                                            )}
                                                            {isUserSelected && !isOptionCorrect && (
                                                                <span className="review-answer-tag review-answer-tag--wrong">
                                                                    Tu respuesta
                                                                </span>
                                                            )}
                                                            <div className="review-answer-text">
                                                                <strong>{opt.option_letter})</strong>{" "}
                                                                {opt.option_text}
                                                            </div>
                                                        </div>
                                                    );
                                                })
                                            ) : (
                                                <>
                                                    {/* OPCIÓN MARCADA POR EL ESTUDIANTE */}
                                                    {q.user_selected_option ? (
                                                        <div
                                                            className={`review-answer-item ${
                                                                isCorrect
                                                                    ? "review-answer-item--correct"
                                                                    : "review-answer-item--wrong"
                                                            }`}
                                                        >
                                                            <span
                                                                className={`review-answer-tag ${
                                                                    isCorrect
                                                                        ? "review-answer-tag--correct"
                                                                        : "review-answer-tag--wrong"
                                                                }`}
                                                            >
                                                                Tu respuesta
                                                            </span>
                                                            <div className="review-answer-text">
                                                                <strong>
                                                                    Opción {q.user_selected_option.option_letter}:
                                                                </strong>{" "}
                                                                {q.user_selected_option.option_text}
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <div className="review-answer-item review-answer-item--omitted">
                                                            <span className="review-answer-tag review-answer-tag--omitted">
                                                                Sin responder
                                                            </span>
                                                            <div className="review-answer-text">
                                                                No seleccionaste ninguna opción para esta pregunta (omitida).
                                                            </div>
                                                        </div>
                                                    )}

                                                    {/* OPCIÓN CORRECTA (SI ES DISTINTA O SI FUE OMITIDA / FALLADA) */}
                                                    {q.correct_option && (
                                                        <div className="review-answer-item review-answer-item--correct">
                                                            <span className="review-answer-tag review-answer-tag--correct">
                                                                Respuesta Correcta
                                                            </span>
                                                            <div className="review-answer-text">
                                                                <strong>
                                                                    Opción {q.correct_option.option_letter}:
                                                                </strong>{" "}
                                                                {q.correct_option.option_text}
                                                            </div>
                                                        </div>
                                                    )}
                                                </>
                                            )}
                                        </div>

                                        {/* 2. EXPLICACIÓN OFICIAL ICFES */}
                                        {q.explanation ? (
                                            <div className="review-explanation-box">
                                                <div className="review-explanation-title">
                                                    <i className="fas fa-lightbulb"></i> Explicación Oficial ICFES
                                                </div>
                                                <p>{q.explanation}</p>
                                            </div>
                                        ) : (
                                            <div className="review-explanation-box">
                                                <p style={{ color: "var(--text-muted)" }}>
                                                    No hay explicación adicional registrada para esta pregunta.
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </article>
                        );
                    })
                )}
            </section>
        </div>
    );
};

export default MockReview;
