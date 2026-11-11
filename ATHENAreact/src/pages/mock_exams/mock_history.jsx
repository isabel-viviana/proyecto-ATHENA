import "./mock_history.css";
import { useEffect, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import Zorrito from "../../assets/images/Zorrito2D.png";
import { getExamHistory } from "../../api/exams";

const formatDuration = (seconds) => {
    if (!seconds || seconds <= 0) return "0 seg";
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    if (mins === 0) return `${secs} seg`;
    if (secs === 0) return `${mins} min`;
    return `${mins} min ${secs} seg`;
};

const formatDate = (isoString) => {
    if (!isoString) return "Fecha no disponible";
    try {
        const d = new Date(isoString);
        return new Intl.DateTimeFormat("es-CO", {
            day: "numeric",
            month: "short",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit",
            hour12: true,
        }).format(d);
    } catch {
        return isoString;
    }
};

const MockHistory = () => {
    const navigate = useNavigate();
    const [history, setHistory] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");

    useEffect(() => {
        const fetchHistory = async () => {
            try {
                setLoading(true);
                setError("");
                const res = await getExamHistory();
                console.log("HISTORIAL RECIBIDO:", res);

                const data = res?.data || res || [];
                setHistory(Array.isArray(data) ? data : []);
            } catch (err) {
                console.error("Error al cargar historial de simulacros:", err);
                setError(
                    err?.response?.data?.message ||
                        err?.message ||
                        "No se pudo cargar el historial de simulacros."
                );
            } finally {
                setLoading(false);
            }
        };

        fetchHistory();
    }, []);

    return (
        <div className="main__content" style={{ maxWidth: "1000px", margin: "0 auto", padding: "30px 24px" }}>
            {/* CABECERA */}
            <section className="page-header" style={{ display: "flex", justifyContent: "space-between", alignItems: "center", flexWrap: "wrap", gap: "16px", marginBottom: "28px" }}>
                <div className="page-header__info">
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
                        marginBottom: "8px",
                    }}>
                        EVALUACIONES OFICIALES
                    </span>
                    <h1 style={{ margin: 0, fontSize: "28px" }}>Historial de Simulacros</h1>
                    <p style={{ color: "#94a3b8", fontSize: "14px", marginTop: "4px" }}>
                        Registro completo de intentos presentados, puntajes y revisiones pedagógicas.
                    </p>
                </div>

                <Link to="/mock/config">
                    <button
                        type="button"
                        style={{
                            backgroundColor: "#0284c7",
                            color: "#ffffff",
                            border: "none",
                            borderRadius: "10px",
                            padding: "10px 18px",
                            fontWeight: 700,
                            fontSize: "14px",
                            cursor: "pointer",
                            display: "inline-flex",
                            alignItems: "center",
                            gap: "8px",
                            transition: "all 0.15s ease",
                        }}
                    >
                        <i className="fas fa-plus"></i>
                        Nuevo Simulacro
                    </button>
                </Link>
            </section>

            {/* ESTADO DE CARGA */}
            {loading && (
                <div style={{ textAlign: "center", padding: "60px 20px" }}>
                    <div style={{ fontSize: "36px", color: "#38bdf8", marginBottom: "16px" }}>
                        <i className="fas fa-spinner fa-spin"></i>
                    </div>
                    <p style={{ color: "#94a3b8" }}>Consultando tu historial oficial de simulacros...</p>
                </div>
            )}

            {/* ERROR */}
            {!loading && error && (
                <div style={{
                    padding: "18px 24px",
                    backgroundColor: "rgba(239, 68, 68, 0.15)",
                    border: "1px solid #ef4444",
                    borderRadius: "12px",
                    color: "#fca5a5",
                    textAlign: "center",
                    marginBottom: "24px",
                }}>
                    <i className="fas fa-triangle-exclamation" style={{ fontSize: "24px", marginBottom: "8px", display: "block" }}></i>
                    <strong>{error}</strong>
                    <div style={{ marginTop: "14px" }}>
                        <button
                            type="button"
                            onClick={() => window.location.reload()}
                            style={{
                                backgroundColor: "rgba(239, 68, 68, 0.3)",
                                border: "1px solid #ef4444",
                                color: "#ffffff",
                                padding: "6px 14px",
                                borderRadius: "8px",
                                cursor: "pointer",
                                fontSize: "13px",
                            }}
                        >
                            Reintentar
                        </button>
                    </div>
                </div>
            )}

            {/* SIN INTENTOS REGISTRADOS (ESTADO VACÍO) */}
            {!loading && !error && history.length === 0 && (
                <section
                    style={{
                        backgroundColor: "rgba(15, 23, 42, 0.6)",
                        border: "1px solid rgba(148, 163, 184, 0.2)",
                        borderRadius: "16px",
                        padding: "50px 24px",
                        textAlign: "center",
                    }}
                >
                    <div style={{ width: "80px", height: "80px", borderRadius: "50%", backgroundColor: "rgba(56, 189, 248, 0.15)", color: "#38bdf8", display: "inline-flex", alignItems: "center", justifyContent: "center", fontSize: "32px", marginBottom: "16px" }}>
                        <i className="fas fa-clipboard-list"></i>
                    </div>
                    <h2 style={{ fontSize: "20px", fontWeight: 700, margin: "0 0 8px", color: "#f8fafc" }}>
                        Aún no has presentado ningún simulacro
                    </h2>
                    <p style={{ color: "#94a3b8", maxWidth: "480px", margin: "0 auto 24px", fontSize: "14px", lineHeight: "1.6" }}>
                        Pon a prueba tus competencias en Lectura Crítica con cuadernillos estructurados, tiempo límite y calificación automática.
                    </p>
                    <button
                        type="button"
                        onClick={() => navigate("/mock/config")}
                        style={{
                            backgroundColor: "#0284c7",
                            color: "#ffffff",
                            border: "none",
                            borderRadius: "10px",
                            padding: "12px 24px",
                            fontWeight: 700,
                            fontSize: "14px",
                            cursor: "pointer",
                            display: "inline-flex",
                            alignItems: "center",
                            gap: "8px",
                        }}
                    >
                        <i className="fas fa-play"></i>
                        Configurar mi Primer Simulacro
                    </button>
                </section>
            )}

            {/* LISTA DE INTENTOS */}
            {!loading && !error && history.length > 0 && (
                <section className="history" style={{ display: "flex", flexDirection: "column", gap: "14px", background: "none", border: "none" }}>
                    {history.map((item, idx) => {
                        const attemptNumber = history.length - idx;
                        const score = typeof item.score === "number" ? item.score : 0;
                        const accuracy = typeof item.accuracy === "number" ? Math.round(item.accuracy) : 0;

                        return (
                            <article
                                key={item.id}
                                className="history-card"
                                style={{
                                    backgroundColor: "rgba(15, 23, 42, 0.7)",
                                    border: "1px solid rgba(148, 163, 184, 0.2)",
                                    borderRadius: "14px",
                                    padding: "20px 24px",
                                    display: "flex",
                                    justifyContent: "space-between",
                                    alignItems: "center",
                                    flexWrap: "wrap",
                                    gap: "18px",
                                    transition: "all 0.15s ease",
                                }}
                            >
                                {/* IZQUIERDA: ÍNDICE Y DATOS BÁSICOS */}
                                <div className="history-card__left" style={{ display: "flex", alignItems: "center", gap: "16px" }}>
                                    <div
                                        className="history-card__number"
                                        style={{
                                            width: "42px",
                                            height: "42px",
                                            borderRadius: "12px",
                                            backgroundColor: "rgba(56, 189, 248, 0.15)",
                                            border: "1px solid rgba(56, 189, 248, 0.3)",
                                            color: "#38bdf8",
                                            fontSize: "15px",
                                            fontWeight: 800,
                                        }}
                                    >
                                        #{attemptNumber}
                                    </div>

                                    <div className="history-card__info">
                                        <div style={{ display: "flex", alignItems: "center", gap: "8px", flexWrap: "wrap" }}>
                                            <h3 style={{ margin: 0, fontSize: "17px", fontWeight: 700, color: "#f8fafc" }}>
                                                {item.title || "Simulacro Lectura Crítica"}
                                            </h3>
                                            <span style={{
                                                fontSize: "11px",
                                                fontWeight: 700,
                                                padding: "2px 8px",
                                                borderRadius: "4px",
                                                backgroundColor: "rgba(59, 130, 246, 0.2)",
                                                color: "#60a5fa",
                                            }}>
                                                {item.subject_name || "Lectura Crítica"}
                                            </span>
                                        </div>

                                        <p style={{ margin: "4px 0 0", fontSize: "13px", color: "#94a3b8" }}>
                                            <i className="far fa-calendar-alt" style={{ marginRight: "6px" }}></i>
                                            {formatDate(item.finished_at || item.started_at)}
                                            <span style={{ margin: "0 8px", color: "rgba(148, 163, 184, 0.4)" }}>•</span>
                                            <i className="fas fa-clock" style={{ marginRight: "6px" }}></i>
                                            {formatDuration(item.total_time_seconds)}
                                            <span style={{ margin: "0 8px", color: "rgba(148, 163, 184, 0.4)" }}>•</span>
                                            {item.questions_count} preguntas
                                        </p>

                                        {/* DESGLOSE RÁPIDO */}
                                        <div style={{ display: "flex", gap: "8px", marginTop: "8px" }}>
                                            <span style={{ fontSize: "11px", color: "#4ade80", backgroundColor: "rgba(34, 197, 94, 0.15)", padding: "2px 6px", borderRadius: "4px" }}>
                                                <i className="fas fa-check" style={{ marginRight: "4px" }}></i>
                                                {item.correct_answers} correctas
                                            </span>
                                            <span style={{ fontSize: "11px", color: "#f87171", backgroundColor: "rgba(239, 68, 68, 0.15)", padding: "2px 6px", borderRadius: "4px" }}>
                                                <i className="fas fa-xmark" style={{ marginRight: "4px" }}></i>
                                                {item.incorrect_answers} incorrectas
                                            </span>
                                            {item.omitted_answers > 0 && (
                                                <span style={{ fontSize: "11px", color: "#cbd5e1", backgroundColor: "rgba(148, 163, 184, 0.15)", padding: "2px 6px", borderRadius: "4px" }}>
                                                    {item.omitted_answers} omitidas
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                {/* DERECHA: PUNTAJE Y BOTONES */}
                                <div className="history-card__right" style={{ display: "flex", alignItems: "center", gap: "20px" }}>
                                    <div className="history-card__score" style={{ textAlign: "right" }}>
                                        <div style={{ display: "flex", alignItems: "baseline", gap: "4px" }}>
                                            <span className="score" style={{ fontSize: "30px", fontWeight: 800, color: "#f8fafc" }}>
                                                {score}
                                            </span>
                                            <span style={{ fontSize: "12px", color: "#94a3b8", fontWeight: 600 }}>/ 100</span>
                                        </div>
                                        <span className="points" style={{ fontSize: "11px", color: "#38bdf8", fontWeight: 700 }}>
                                            {accuracy}% PRECISIÓN
                                        </span>
                                    </div>

                                    <div style={{ display: "flex", gap: "8px" }}>
                                        <Link to={`/mock/result?id=${item.id}`}>
                                            <button
                                                type="button"
                                                className="history-card__btn"
                                                style={{
                                                    padding: "8px 14px",
                                                    fontSize: "13px",
                                                    fontWeight: 600,
                                                    borderRadius: "8px",
                                                    backgroundColor: "rgba(30, 41, 59, 0.8)",
                                                    border: "1px solid rgba(148, 163, 184, 0.25)",
                                                    color: "#f8fafc",
                                                    cursor: "pointer",
                                                }}
                                            >
                                                Resultados
                                            </button>
                                        </Link>

                                        <Link to={`/mock/review?id=${item.id}`}>
                                            <button
                                                type="button"
                                                style={{
                                                    padding: "8px 14px",
                                                    fontSize: "13px",
                                                    fontWeight: 600,
                                                    borderRadius: "8px",
                                                    backgroundColor: "rgba(56, 189, 248, 0.15)",
                                                    border: "1px solid rgba(56, 189, 248, 0.4)",
                                                    color: "#38bdf8",
                                                    cursor: "pointer",
                                                }}
                                            >
                                                Revisar
                                            </button>
                                        </Link>
                                    </div>
                                </div>
                            </article>
                        );
                    })}
                </section>
            )}

            {/* ASISTENTE ZORRITO FLOTANTE */}
            <div className="assistant">
                <div className="assistant__message">
                    {history.length > 0
                        ? `¡Llevas ${history.length} simulacro(s) completado(s)! Revisa tus fallos para mejorar tu puntaje.`
                        : "¡Bienvenido al módulo de simulacros ICFES! Configura tu prueba cuando estés listo."}
                </div>
                <div className="assistant__bot">
                    <img src={Zorrito} alt="Zorrito Athena" />
                </div>
            </div>
        </div>
    );
};

export default MockHistory;
