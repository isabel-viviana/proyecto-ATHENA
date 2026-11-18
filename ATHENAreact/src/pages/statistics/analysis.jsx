import { useEffect } from "react";
import { Link } from "react-router-dom";
import { useShallow } from "zustand/react/shallow";
import useProgressStore from "../../stores/useProgressStore";
import "./analysis.css";

const formatShortDate = (dateStr) => {
    if (!dateStr) return "Reciente";
    try {
        const d = new Date(dateStr);
        if (Number.isNaN(d.getTime())) return String(dateStr).slice(0, 10);
        return new Intl.DateTimeFormat("es-CO", {
            day: "numeric",
            month: "short",
            year: "numeric",
        }).format(d);
    } catch {
        return String(dateStr).slice(0, 10);
    }
};

const Analysis = () => {
    const { fetchDashboardProgress, loading, error, dashboard } = useProgressStore(
        useShallow((state) => ({
            fetchDashboardProgress: state.fetchDashboardProgress,
            loading: state.loading,
            error: state.error,
            dashboard: state.dashboard,
        }))
    );

    useEffect(() => {
        fetchDashboardProgress("all");
    }, [fetchDashboardProgress]);

    const summary = dashboard?.summary || {};
    const academicAnalysis = dashboard?.academic_analysis || {};
    const bySubject = dashboard?.by_subject || [];
    const weakTopics =
        academicAnalysis.weak_topics ||
        dashboard?.weak_topics ||
        [];
    const strongTopics =
        academicAnalysis.strong_topics ||
        dashboard?.strong_topics ||
        [];
    const recommendations = academicAnalysis.study_recommendations || [];
    const recentExams = dashboard?.recent_exams || [];

    const totalAnswered = Number(summary.total_answered || 0);
    const completedExamsCount = Number(summary.completed_exams_count || 0);
    const accuracy = Number(summary.accuracy || 0);
    const minRequired = Number(academicAnalysis.minimum_questions_required || 3);
    const hasSufficientData =
        typeof academicAnalysis.has_sufficient_data === "boolean"
            ? academicAnalysis.has_sufficient_data
            : totalAnswered >= minRequired;

    // Áreas evaluadas ordenadas de menor a mayor rendimiento
    const practicedAreasSorted = bySubject
        .filter((s) => Number(s.total_answered || 0) > 0)
        .sort((a, b) => Number(a.accuracy || 0) - Number(b.accuracy || 0));

    if (loading && !dashboard) {
        return (
            <div className="main__content">
                <section className="section-header">
                    <h2>Análisis Diagnóstico Académico</h2>
                </section>
                <section className="card card--diagnostic" style={{ textAlign: "center", padding: "50px 24px" }}>
                    <div style={{ fontSize: "34px", color: "#38bdf8", marginBottom: "14px" }}>
                        <i className="fas fa-spinner fa-spin"></i>
                    </div>
                    <h3>Analizando tus respuestas oficiales...</h3>
                    <p style={{ color: "#94a3b8", marginTop: "8px" }}>
                        Consultando tu desempeño por áreas y temas en Question Bank.
                    </p>
                </section>
            </div>
        );
    }

    if (error && !dashboard) {
        return (
            <div className="main__content">
                <section className="section-header">
                    <h2>Análisis Diagnóstico Académico</h2>
                </section>
                <section className="card card--diagnostic" style={{ textAlign: "center", padding: "40px 24px" }}>
                    <div style={{ fontSize: "34px", color: "#ef4444", marginBottom: "14px" }}>
                        <i className="fas fa-triangle-exclamation"></i>
                    </div>
                    <h3>No fue posible cargar el diagnóstico</h3>
                    <p style={{ color: "#fca5a5", margin: "10px 0 20px" }}>{error}</p>
                    <button
                        type="button"
                        className="topic-card__btn"
                        onClick={() => fetchDashboardProgress("all")}
                        style={{ margin: "0 auto", border: "none", cursor: "pointer" }}
                    >
                        <i className="fas fa-rotate-right"></i> Reintentar
                    </button>
                </section>
            </div>
        );
    }

    return (
        <div className="main__content">
            {/* TÍTULO Y ENLACES RÁPIDOS */}
            <section
                className="section-header"
                style={{
                    display: "flex",
                    justifyContent: "space-between",
                    alignItems: "center",
                    flexWrap: "wrap",
                    gap: "12px",
                }}
            >
                <div>
                    <h2>Análisis Diagnóstico Académico</h2>
                    <p style={{ color: "#94a3b8", fontSize: "14px", marginTop: "4px" }}>
                        Diagnóstico calculado a partir de tus respuestas reales en prácticas y simulacros ICFES.
                    </p>
                </div>
                <div style={{ display: "flex", gap: "10px" }}>
                    <Link to="/statistics" className="topic-card__btn">
                        <i className="fas fa-chart-pie"></i> Ver Estadísticas
                    </Link>
                    <Link to="/dashboard" className="topic-card__btn">
                        <i className="fas fa-house"></i> Dashboard
                    </Link>
                </div>
            </section>

            {/* SI NO EXISTEN SUFICIENTES RESPUESTAS PARA GENERAR UN DIAGNÓSTICO */}
            {!hasSufficientData ? (
                <section
                    className="card card--diagnostic"
                    style={{ textAlign: "center", padding: "44px 28px" }}
                >
                    <div
                        className="card__icon"
                        style={{ margin: "0 auto 16px", width: "56px", height: "56px", fontSize: "24px" }}
                    >
                        <i className="fas fa-clipboard-question"></i>
                    </div>
                    <h3 style={{ fontSize: "20px", marginBottom: "10px", color: "#f8fafc" }}>
                        Necesitas completar más prácticas o simulacros para generar tu diagnóstico
                    </h3>
                    <p style={{ maxWidth: "560px", margin: "0 auto 22px", color: "#94a3b8" }}>
                        Actualmente registras <strong>{totalAnswered}</strong>{" "}
                        {totalAnswered === 1 ? "pregunta respondida" : "preguntas respondidas"}. Para
                        identificar con precisión tus áreas de menor rendimiento, temas con dificultades
                        y recomendaciones de estudio reales, debes resolver al menos{" "}
                        <strong>{minRequired} preguntas oficiales</strong> en Prácticas o presentar un
                        Simulacro.
                    </p>

                    <div
                        style={{
                            display: "flex",
                            justifyContent: "center",
                            gap: "12px",
                            flexWrap: "wrap",
                        }}
                    >
                        <Link to="/practices" className="topic-card__btn" style={{ padding: "10px 20px" }}>
                            <i className="fas fa-dumbbell"></i> Ir a Prácticas ICFES
                        </Link>
                        <Link to="/mock/quick" className="topic-card__btn" style={{ padding: "10px 20px" }}>
                            <i className="fas fa-bolt"></i> Iniciar Mini Simulacro Rápido
                        </Link>
                    </div>
                </section>
            ) : (
                <>
                    {/* 1. DIAGNÓSTICO GENERAL BASADO EN DATOS REALES */}
                    <section className="card card--diagnostic">
                        <div className="card__header">
                            <div className="card__icon">
                                <i className="fas fa-lightbulb"></i>
                            </div>
                            <h3>Diagnóstico ATHENA</h3>
                        </div>
                        <p>{academicAnalysis.pedagogical_advice}</p>

                        <div
                            style={{
                                display: "grid",
                                gridTemplateColumns: "repeat(auto-fit, minmax(150px, 1fr))",
                                gap: "12px",
                                marginTop: "20px",
                                paddingTop: "16px",
                                borderTop: "1px solid rgba(148, 163, 184, 0.15)",
                            }}
                        >
                            <div>
                                <span style={{ fontSize: "12px", color: "#94a3b8", display: "block" }}>
                                    Reactivos Evaluados
                                </span>
                                <strong style={{ fontSize: "20px", color: "#f8fafc" }}>
                                    {totalAnswered}
                                </strong>
                            </div>
                            <div>
                                <span style={{ fontSize: "12px", color: "#94a3b8", display: "block" }}>
                                    Precisión Global
                                </span>
                                <strong
                                    style={{
                                        fontSize: "20px",
                                        color: accuracy >= 70 ? "#4ade80" : "#38bdf8",
                                    }}
                                >
                                    {accuracy}%
                                </strong>
                            </div>
                            <div>
                                <span style={{ fontSize: "12px", color: "#94a3b8", display: "block" }}>
                                    Simulacros Completados
                                </span>
                                <strong style={{ fontSize: "20px", color: "#f8fafc" }}>
                                    {completedExamsCount}
                                </strong>
                            </div>
                            <div>
                                <span style={{ fontSize: "12px", color: "#94a3b8", display: "block" }}>
                                    Temas Críticos
                                </span>
                                <strong
                                    style={{
                                        fontSize: "20px",
                                        color: weakTopics.length > 0 ? "#f87171" : "#4ade80",
                                    }}
                                >
                                    {weakTopics.length}
                                </strong>
                            </div>
                        </div>
                    </section>

                    {/* 2. ÁREAS DE MENOR RENDIMIENTO Y DESEMPEÑO POR ÁREA */}
                    <section className="critical-topics">
                        <h3 className="critical-topics__title">
                            Desempeño por Áreas Evaluadas{" "}
                            <span>(Ordenadas de menor a mayor efectividad)</span>
                        </h3>

                        {practicedAreasSorted.map((area) => {
                            const acc = Number(area.accuracy || 0);
                            const errRate = Number(
                                area.error_rate ?? Math.round((100 - acc) * 10) / 10
                            );
                            const isWeak = acc < 70;
                            const practiceLink = `/practices-full?subject_id=${
                                area.subject_id
                            }&subject_name=${encodeURIComponent(area.subject_name)}`;

                            return (
                                <div className="topic-card" key={area.subject_id}>
                                    <div className="topic-card__info">
                                        <h4>{area.subject_name}</h4>
                                        <span>
                                            {area.correct_count} aciertos de {area.total_answered}{" "}
                                            respondidas ({acc}% efectividad)
                                        </span>
                                    </div>
                                    <div className="topic-card__stat">
                                        <span
                                            className={`badge ${
                                                errRate >= 50
                                                    ? "badge--red"
                                                    : isWeak
                                                    ? "badge--yellow"
                                                    : ""
                                            }`}
                                            style={
                                                !isWeak
                                                    ? {
                                                          backgroundColor: "rgba(34, 197, 94, 0.15)",
                                                          color: "#4ade80",
                                                      }
                                                    : undefined
                                            }
                                        >
                                            {isWeak
                                                ? `${errRate}% de error`
                                                : `${acc}% dominio`}
                                        </span>
                                    </div>
                                    <Link to={practiceLink} className="topic-card__btn">
                                        Practicar área <i className="fas fa-arrow-right"></i>
                                    </Link>
                                </div>
                            );
                        })}
                    </section>

                    {/* 3. TEMAS CON DIFICULTADES */}
                    <section className="critical-topics">
                        <h3 className="critical-topics__title">
                            Temas con Dificultades <span>(Identificados según tus fallos reales)</span>
                        </h3>

                        {weakTopics.length > 0 ? (
                            weakTopics.map((topic, idx) => {
                                const errRate = Number(
                                    topic.error_rate ?? Math.round(100 - Number(topic.accuracy || 0))
                                );
                                const practiceLink = `/practices-full?subject_id=${
                                    topic.subject_id || 1
                                }&subject_name=${encodeURIComponent(
                                    topic.subject_name || "Lectura Crítica"
                                )}`;

                                return (
                                    <div
                                        className="topic-card"
                                        key={topic.topic_id || topic.topic_name || idx}
                                    >
                                        <div className="topic-card__info">
                                            <h4>{topic.topic_name || topic.name}</h4>
                                            <span>
                                                {topic.subject_name} · {topic.incorrect_count} fallo(s)
                                                de {topic.total_answered} intento(s) ({topic.accuracy}%
                                                acierto)
                                            </span>
                                        </div>
                                        <div className="topic-card__stat">
                                            <span
                                                className={`badge ${
                                                    errRate >= 60 ? "badge--red" : "badge--yellow"
                                                }`}
                                            >
                                                {errRate}% de error
                                            </span>
                                        </div>
                                        <Link to={practiceLink} className="topic-card__btn">
                                            Practicar esto <i className="fas fa-arrow-right"></i>
                                        </Link>
                                    </div>
                                );
                            })
                        ) : (
                            <div
                                className="topic-card"
                                style={{
                                    borderColor: "rgba(34, 197, 94, 0.35)",
                                    background: "rgba(34, 197, 94, 0.06)",
                                }}
                            >
                                <div className="topic-card__info">
                                    <h4 style={{ color: "#4ade80" }}>
                                        ¡Sin temas críticos con alto porcentaje de error!
                                    </h4>
                                    <span>
                                        Todos los temas que has practicado mantienen una efectividad
                                        igual o superior al 70% ({strongTopics.length}{" "}
                                        {strongTopics.length === 1
                                            ? "tema dominado"
                                            : "temas dominados"}
                                        ).
                                    </span>
                                </div>
                                <Link to="/mock/quick" className="topic-card__btn">
                                    Mini Simulacro <i className="fas fa-arrow-right"></i>
                                </Link>
                            </div>
                        )}
                    </section>

                    {/* 4. RECOMENDACIONES DE ESTUDIO BASADAS EN DATOS REALES */}
                    <section className="critical-topics">
                        <h3 className="critical-topics__title">
                            Recomendaciones de Estudio <span>(Plan de acción personalizado)</span>
                        </h3>

                        {recommendations.length > 0 ? (
                            recommendations.map((rec, idx) => (
                                <div className="topic-card" key={idx}>
                                    <div className="topic-card__info">
                                        <h4>{rec.title}</h4>
                                        <span>{rec.description}</span>
                                    </div>
                                    <div className="topic-card__stat">
                                        <span
                                            className={`badge ${
                                                rec.priority === "alta"
                                                    ? "badge--red"
                                                    : "badge--yellow"
                                            }`}
                                        >
                                            Prioridad {rec.priority || "media"}
                                        </span>
                                    </div>
                                    <Link
                                        to={rec.action_url || "/practices"}
                                        className="topic-card__btn"
                                    >
                                        {rec.action_label || "Practicar"}{" "}
                                        <i className="fas fa-arrow-right"></i>
                                    </Link>
                                </div>
                            ))
                        ) : (
                            <div className="topic-card">
                                <div className="topic-card__info">
                                    <h4>Continúa entrenando con Simulacros ICFES</h4>
                                    <span>
                                        Mantén tu ritmo actual practicando nuevos reactivos del banco
                                        oficial.
                                    </span>
                                </div>
                                <Link to="/mock/quick" className="topic-card__btn">
                                    Iniciar Simulacro <i className="fas fa-arrow-right"></i>
                                </Link>
                            </div>
                        )}
                    </section>

                    {/* 5. RESULTADOS DISPONIBLES DE SIMULACROS */}
                    <section className="critical-topics">
                        <h3 className="critical-topics__title">
                            Resultados Disponibles <span>(Simulacros finalizados)</span>
                        </h3>

                        {recentExams.length > 0 ? (
                            recentExams.slice(0, 5).map((exam) => (
                                <div className="topic-card" key={exam.id}>
                                    <div className="topic-card__info">
                                        <h4>{exam.title || `Simulacro ${exam.subject_name}`}</h4>
                                        <span>
                                            {formatShortDate(exam.finished_at || exam.created_at)} ·{" "}
                                            {exam.correct_answers}/{exam.questions_count} aciertos ·
                                            Puntaje: {exam.score}
                                        </span>
                                    </div>
                                    <div className="topic-card__stat">
                                        <span
                                            className={`badge ${
                                                Number(exam.accuracy || 0) >= 70
                                                    ? ""
                                                    : "badge--yellow"
                                            }`}
                                            style={
                                                Number(exam.accuracy || 0) >= 70
                                                    ? {
                                                          backgroundColor: "rgba(34, 197, 94, 0.15)",
                                                          color: "#4ade80",
                                                      }
                                                    : undefined
                                            }
                                        >
                                            {exam.accuracy}% precisión
                                        </span>
                                    </div>
                                    <Link
                                        to={`/mock/result?id=${exam.id}`}
                                        className="topic-card__btn"
                                    >
                                        Ver resultado <i className="fas fa-arrow-right"></i>
                                    </Link>
                                </div>
                            ))
                        ) : (
                            <div className="topic-card">
                                <div className="topic-card__info">
                                    <h4>Aún no has finalizado ningún simulacro</h4>
                                    <span>
                                        Tus diagnósticos actuales provienen de tus sesiones de
                                        práctica. Presenta un Mini Simulacro Rápido para evaluar tu
                                        manejo del tiempo.
                                    </span>
                                </div>
                                <Link to="/mock/quick" className="topic-card__btn">
                                    Mini Simulacro Rápido <i className="fas fa-arrow-right"></i>
                                </Link>
                            </div>
                        )}
                    </section>

                    {/* MENSAJE FINAL */}
                    <div className="motivation">
                        <p>
                            {accuracy >= 70
                                ? `¡Excelente desempeño (${accuracy}% de precisión)! Sigue consolidando tu preparación ICFES.`
                                : `Llevas ${totalAnswered} reactivos analizados. Enfócate en los temas recomendados para elevar tu puntaje.`}
                        </p>
                    </div>
                </>
            )}
        </div>
    );
};

export default Analysis;
