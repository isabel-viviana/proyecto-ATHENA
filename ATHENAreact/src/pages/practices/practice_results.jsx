import "./practice_results.css";
import { useEffect, useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { getExamResults } from "../../api/exams";

const PracticeResults = () => {
    const [searchParams] = useSearchParams();
    const practiceId = searchParams.get("id");

    const [results, setResults] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");

    useEffect(() => {
        const loadResults = async () => {
            if (!practiceId) {
                setError("No se encontró el identificador de la práctica.");
                setLoading(false);
                return;
            }

            try {
                setLoading(true);
                setError("");

                const response = await getExamResults(practiceId);

                const data = response?.data ?? response;
                setResults(data);
            } catch (err) {
                console.error("Error cargando resultados de práctica:", err);
                setError(
                    err?.response?.data?.message ||
                        err?.message ||
                        "No fue posible consultar los resultados de la práctica."
                );
            } finally {
                setLoading(false);
            }
        };

        loadResults();
    }, [practiceId]);

    const formatDuration = (totalSeconds) => {
        if (!totalSeconds || totalSeconds <= 0) return "0 min";
        const mins = Math.floor(totalSeconds / 60);
        const secs = totalSeconds % 60;
        if (mins === 0) return `${secs} seg`;
        return `${mins} min ${secs > 0 ? `${secs}s` : ""}`;
    };

    if (loading) {
        return (
            <section className="success-wrapper">
                <div className="success-card">
                    <div className="success-icon" style={{ borderColor: "#38bdf8", color: "#38bdf8" }}>
                        <i className="fas fa-spinner fa-spin"></i>
                    </div>
                    <h1>Calculando Desempeño...</h1>
                    <p>Estamos procesando tus respuestas y métricas de práctica.</p>
                </div>
            </section>
        );
    }

    if (error) {
        return (
            <section className="success-wrapper">
                <div className="success-card">
                    <div className="success-icon" style={{ borderColor: "#ef4444", color: "#ef4444" }}>
                        <i className="fas fa-triangle-exclamation"></i>
                    </div>
                    <h1>Error de Consulta</h1>
                    <p>{error}</p>
                    <div className="success-buttons" style={{ marginTop: "24px" }}>
                        <Link to="/practices">
                            <button className="btn btn-primary">Volver a Prácticas</button>
                        </Link>
                    </div>
                </div>
            </section>
        );
    }

    // Extracción limpia desde el contrato canónico de Laravel
    const correct = results?.correct_answers ?? 0;
    const incorrect = results?.incorrect_answers ?? 0;
    const omitted = results?.omitted_answers ?? 0;
    const total = results?.total_questions ?? (correct + incorrect + omitted);
    // Rendimiento porcentual del estudiante (0% a 100%, no escala sobre 500)
    const accuracy = results?.score_percentage ?? (total > 0 ? Math.round((correct / total) * 100) : 0);
    const duration = formatDuration(results?.duration_seconds);
    const subjectName = results?.subject_performance?.[0]?.subject_name || "Área Practicada";
    const topicPerformance = results?.topic_performance || [];

    return (
        <section className="success-wrapper">
            <div className="success-card">
                <div className="success-icon">
                    <i className="fas fa-bullseye"></i>
                </div>

                <h1>¡Práctica Completada!</h1>

                <p>
                    Has finalizado tu sesión oficial de práctica en <strong>{subjectName}</strong>.
                </p>

                <div className="success-stats">
                    <div className="mini-box">
                        <span className="mini-box__label">Aciertos</span>
                        <h3>
                            {correct} <small style={{ fontSize: "14px", color: "#94a3b8" }}>/ {total}</small>
                        </h3>
                    </div>

                    <div className="mini-box">
                        <span className="mini-box__label">Precisión</span>
                        <h3>{accuracy}%</h3>
                    </div>

                    <div className="mini-box">
                        <span className="mini-box__label">Tiempo Empleado</span>
                        <h3>{duration}</h3>
                    </div>
                </div>

                {/* RESUMEN DE PREGUNTAS (INCLUYENDO OMITIDAS) */}
                <div style={{ display: "flex", justifyContent: "center", gap: "24px", marginTop: "24px", color: "#94a3b8", fontSize: "14px", flexWrap: "wrap" }}>
                    <span>
                        <i className="fas fa-check" style={{ color: "#10b981", marginRight: "6px" }}></i>
                        {correct} correctas
                    </span>
                    <span>
                        <i className="fas fa-xmark" style={{ color: "#ef4444", marginRight: "6px" }}></i>
                        {incorrect} incorrectas
                    </span>
                    <span>
                        <i className="fas fa-minus" style={{ color: "#facc15", marginRight: "6px" }}></i>
                        {omitted} omitidas
                    </span>
                </div>

                {/* DESGLOSE POR TEMA (SI EXISTE RENDIMIENTO POR EJE TEMÁTICO) */}
                {topicPerformance.length > 0 && (
                    <div className="results-breakdown">
                        <div className="results-breakdown__title">
                            <i className="fas fa-chart-pie" style={{ color: "#38bdf8" }}></i>
                            Desglose de Desempeño por Eje Temático
                        </div>

                        <div className="breakdown-list">
                            {topicPerformance.map((tp, idx) => (
                                <div className="breakdown-item" key={tp.topic_id || idx}>
                                    <span className="breakdown-item__name">
                                        {tp.topic_name}
                                    </span>

                                    <div className="breakdown-item__stats">
                                        <span className="breakdown-pill breakdown-pill--correct">
                                            {tp.correct} aciertos
                                        </span>
                                        {tp.omitted > 0 && (
                                            <span className="breakdown-pill breakdown-pill--omitted">
                                                {tp.omitted} omitidas
                                            </span>
                                        )}
                                        <span className="breakdown-pill breakdown-pill--score">
                                            {tp.score_percentage}%
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                <div className="success-buttons" style={{ marginTop: "36px", display: "flex", gap: "14px", justifyContent: "center", flexWrap: "wrap" }}>
                    <Link to={practiceId ? `/practices-review?id=${practiceId}` : "/practices"}>
                        <button className="btn btn-secondary">
                            <i className="fas fa-clipboard-list" style={{ marginRight: "8px" }}></i>
                            Revisar Respuestas Detalladas ({total})
                        </button>
                    </Link>

                    <Link to="/practices">
                        <button className="btn btn-primary">
                            <i className="fas fa-arrow-rotate-right" style={{ marginRight: "8px" }}></i>
                            Nueva Práctica por Área
                        </button>
                    </Link>
                </div>
            </div>
        </section>
    );
};

export default PracticeResults;
