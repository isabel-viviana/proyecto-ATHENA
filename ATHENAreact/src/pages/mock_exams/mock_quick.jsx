import "./mock_quick.css";
import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import Zorrito from "../../assets/images/Zorrito2D.png";
import { configureExam } from "../../api/exams";

const MockQuick = () => {
    const navigate = useNavigate();
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState("");

    const handleStartQuick = async () => {
        try {
            setLoading(true);
            setError("");

            const response = await configureExam({
                exam_type: "quick",
                difficulty: "medium",
                subject_ids: [1, 2, 3, 4, 5],
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
                        ~15 min
                    </div>
                </div>

                {/* INFO CARD */}
                <div className="question-card" style={{ padding: "32px", textAlign: "center" }}>
                    <div style={{ fontSize: "42px", color: "#facc15", marginBottom: "16px" }}>
                        <i className="fas fa-bolt"></i>
                    </div>

                    <h3 style={{ fontSize: "22px", marginBottom: "12px", color: "#f1f5f9" }}>
                        Evaluación Rápida Multiárea
                    </h3>

                    <p style={{ color: "#94a3b8", fontSize: "15px", maxWidth: "560px", margin: "0 auto 24px", lineHeight: "1.6" }}>
                        Prueba ágil para medir tu nivel general en las competencias ICFES. Consta de preguntas seleccionadas del banco real para brindarte un diagnóstico inmediato.
                    </p>

                    <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))", gap: "16px", marginBottom: "28px", textAlign: "left" }}>
                        <div style={{ background: "#16233b", padding: "14px", borderRadius: "10px", border: "1px solid #1e293b" }}>
                            <strong style={{ color: "#60a5fa", display: "block", marginBottom: "4px" }}>
                                <i className="fas fa-list-check" style={{ marginRight: "6px" }}></i>
                                5 Preguntas
                            </strong>
                            <small style={{ color: "#64748b" }}>Muestra rápida de áreas oficiales</small>
                        </div>
                        <div style={{ background: "#16233b", padding: "14px", borderRadius: "10px", border: "1px solid #1e293b" }}>
                            <strong style={{ color: "#10b981", display: "block", marginBottom: "4px" }}>
                                <i className="fas fa-chart-simple" style={{ marginRight: "6px" }}></i>
                                Diagnóstico
                            </strong>
                            <small style={{ color: "#64748b" }}>Retroalimentación inmediata</small>
                        </div>
                        <div style={{ background: "#16233b", padding: "14px", borderRadius: "10px", border: "1px solid #1e293b" }}>
                            <strong style={{ color: "#facc15", display: "block", marginBottom: "4px" }}>
                                <i className="fas fa-database" style={{ marginRight: "6px" }}></i>
                                Banco Real
                            </strong>
                            <small style={{ color: "#64748b" }}>Preguntas de la plataforma</small>
                        </div>
                    </div>

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
