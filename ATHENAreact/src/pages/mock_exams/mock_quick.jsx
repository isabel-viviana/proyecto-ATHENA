import "./mock_quick.css";
import { useState, useEffect } from "react";
import { Link, useNavigate } from "react-router-dom";
import Zorrito from "../../assets/images/Zorrito2D.png";
import { startExam, getExamConfigOptions, getActiveExam } from "../../api/exams";

const MockQuick = () => {
    const navigate = useNavigate();
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState("");
    const [areas, setAreas] = useState([]);
    const [activeAttempt, setActiveAttempt] = useState(null);

    useEffect(() => {
        let isMounted = true;
        const loadQuickMetadata = async () => {
            try {
                const [configRes, activeRes] = await Promise.all([
                    getExamConfigOptions().catch(() => null),
                    getActiveExam().catch(() => null),
                ]);
                if (!isMounted) return;

                if (configRes?.success && Array.isArray(configRes?.data?.areas)) {
                    setAreas(configRes.data.areas);
                }
                if (activeRes?.success && activeRes?.data?.attempt) {
                    setActiveAttempt(activeRes.data.attempt);
                }
            } catch {
                // Silencioso en precarga informativa
            }
        };

        loadQuickMetadata();
        return () => {
            isMounted = false;
        };
    }, []);

    const handleStartQuick = async () => {
        try {
            setLoading(true);
            setError("");

            const response = await startExam({
                mode: "quick",
                exam_type: "quick",
                difficulty: "medium",
                subject_ids: [1, 2, 3, 4, 5],
                questions_count: 5,
                duration_minutes: 15,
            });

            const responseData = response?.data ?? response;
            const examId =
                responseData?.data?.attempt?.id ||
                responseData?.attempt?.id ||
                responseData?.data?.id ||
                responseData?.attempt_id ||
                responseData?.id;

            if (!examId) {
                throw new Error("No se obtuvo el ID del intento de mini simulacro.");
            }

            navigate(`/mock/exam?id=${examId}`);
        } catch (err) {
            console.error("Error al iniciar mini simulacro:", err);
            setError(
                err?.response?.data?.message ||
                    err?.message ||
                    "No se pudo iniciar el simulacro rápido."
            );
        } finally {
            setLoading(false);
        }
    };

    const totalPublished = areas.reduce(
        (acc, a) => acc + Number(a.published_count || 0),
        0
    );

    return (
        <div className="main__content">
            <section className="quiz-container">
                {/* HEADER */}
                <div className="quiz-top">
                    <div className="quiz-top__left">
                        <span className="quiz-top__tag">
                            <i className="fas fa-bolt"></i>
                            SIMULACRO RÁPIDO
                        </span>
                        <h2>Mini Simulacro Diagnóstico</h2>
                    </div>
                    <div className="quiz-top__timer" style={{ fontSize: "14px", color: "#facc15" }}>
                        <i className="fa-regular fa-clock" style={{ marginRight: "6px" }}></i>
                        15 min
                    </div>
                </div>

                {/* RECUPERACIÓN SI HAY UN INTENTO EN PROGRESO */}
                {activeAttempt && (
                    <div
                        style={{
                            background: "rgba(2, 132, 199, 0.18)",
                            border: "1px solid #38bdf8",
                            borderRadius: "12px",
                            padding: "14px 18px",
                            marginBottom: "18px",
                            display: "flex",
                            justifyContent: "space-between",
                            alignItems: "center",
                            flexWrap: "wrap",
                            gap: "12px",
                        }}
                    >
                        <div>
                            <strong style={{ color: "#38bdf8", fontSize: "13px", display: "block" }}>
                                <i className="fas fa-clock-rotate-left" style={{ marginRight: "6px" }}></i>
                                TIENES UN SIMULACRO EN PROGRESO
                            </strong>
                            <span style={{ color: "#f8fafc", fontSize: "14px" }}>
                                {activeAttempt.title} ({activeAttempt.questions_count} preguntas)
                            </span>
                        </div>
                        <button
                            type="button"
                            onClick={() => navigate(`/mock/exam?id=${activeAttempt.id}`)}
                            style={{
                                background: "#0284c7",
                                color: "#ffffff",
                                border: "none",
                                borderRadius: "8px",
                                padding: "8px 16px",
                                fontWeight: 700,
                                cursor: "pointer",
                                fontSize: "13px",
                            }}
                        >
                            Continuar intento activo
                        </button>
                    </div>
                )}

                {/* INFO CARD */}
                <div className="question-card" style={{ padding: "32px", textAlign: "center" }}>
                    <div style={{ fontSize: "42px", color: "#facc15", marginBottom: "16px" }}>
                        <i className="fas fa-bolt"></i>
                    </div>

                    <h3 style={{ fontSize: "22px", marginBottom: "12px", color: "#f1f5f9" }}>
                        Evaluación Rápida Multiárea ICFES
                    </h3>

                    <p style={{ color: "#94a3b8", fontSize: "15px", maxWidth: "560px", margin: "0 auto 24px", lineHeight: "1.6" }}>
                        Prueba ágil para medir tu nivel general en las cinco áreas del ICFES (Lectura Crítica, Matemáticas, Ciencias Naturales, Sociales y Ciudadanas, e Inglés). Selecciona preguntas verificadas del banco oficial para brindarte un diagnóstico inmediato.
                    </p>

                    <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))", gap: "16px", marginBottom: "24px", textAlign: "left" }}>
                        <div style={{ background: "#16233b", padding: "14px", borderRadius: "10px", border: "1px solid #1e293b" }}>
                            <strong style={{ color: "#60a5fa", display: "block", marginBottom: "4px" }}>
                                <i className="fas fa-list-check" style={{ marginRight: "6px" }}></i>
                                5 Preguntas
                            </strong>
                            <small style={{ color: "#64748b" }}>Muestra ágil de las 5 áreas ICFES</small>
                        </div>
                        <div style={{ background: "#16233b", padding: "14px", borderRadius: "10px", border: "1px solid #1e293b" }}>
                            <strong style={{ color: "#10b981", display: "block", marginBottom: "4px" }}>
                                <i className="fas fa-chart-simple" style={{ marginRight: "6px" }}></i>
                                Diagnóstico Real
                            </strong>
                            <small style={{ color: "#64748b" }}>Impacta tu Dashboard y Análisis</small>
                        </div>
                        <div style={{ background: "#16233b", padding: "14px", borderRadius: "10px", border: "1px solid #1e293b" }}>
                            <strong style={{ color: "#facc15", display: "block", marginBottom: "4px" }}>
                                <i className="fas fa-database" style={{ marginRight: "6px" }}></i>
                                Banco Oficial {totalPublished > 0 ? `(${totalPublished})` : ""}
                            </strong>
                            <small style={{ color: "#64748b" }}>Reactivos verificados en plataforma</small>
                        </div>
                    </div>

                    {areas.length > 0 && (
                        <div
                            style={{
                                display: "flex",
                                justifyContent: "center",
                                gap: "8px",
                                flexWrap: "wrap",
                                marginBottom: "24px",
                            }}
                        >
                            {areas.map((a) => (
                                <span
                                    key={a.id}
                                    style={{
                                        fontSize: "12px",
                                        padding: "4px 10px",
                                        borderRadius: "999px",
                                        background: a.available
                                            ? "rgba(34, 197, 94, 0.12)"
                                            : "rgba(148, 163, 184, 0.1)",
                                        border: a.available
                                            ? "1px solid rgba(34, 197, 94, 0.35)"
                                            : "1px solid rgba(148, 163, 184, 0.2)",
                                        color: a.available ? "#4ade80" : "#94a3b8",
                                        fontWeight: 600,
                                    }}
                                >
                                    {a.name}:{" "}
                                    {a.available ? `${a.published_count} activas` : "Próximamente"}
                                </span>
                            ))}
                        </div>
                    )}

                    {error && (
                        <div style={{ background: "rgba(239, 68, 68, 0.1)", border: "1px solid #ef4444", color: "#fca5a5", padding: "12px", borderRadius: "8px", marginBottom: "20px" }}>
                            {error}
                        </div>
                    )}

                    <div style={{ display: "flex", gap: "14px", justifyContent: "center", flexWrap: "wrap" }}>
                        <Link to="/mock/config">
                            <button type="button" className="footer-btn footer-btn--ghost" style={{ padding: "12px 24px" }}>
                                <i className="fas fa-sliders" style={{ marginRight: "8px" }}></i>
                                Personalizar Áreas
                            </button>
                        </Link>

                        <button
                            type="button"
                            className="finish-btn"
                            onClick={handleStartQuick}
                            disabled={loading}
                            style={{ padding: "12px 32px" }}
                        >
                            {loading ? (
                                "Iniciando..."
                            ) : (
                                <>
                                    <i className="fas fa-play" style={{ marginRight: "8px" }}></i>
                                    Comenzar Ahora
                                </>
                            )}
                        </button>
                    </div>
                </div>
            </section>

            {/* ASSISTANT */}
            <div className="assistant">
                <div className="assistant__message">
                    ¡El simulacro rápido te ayuda a mantener el ritmo diario sin tomarte mucho tiempo!
                </div>
                <div className="assistant__bot">
                    <img src={Zorrito} alt="Zorrito" />
                </div>
            </div>
        </div>
    );
};

export default MockQuick;
