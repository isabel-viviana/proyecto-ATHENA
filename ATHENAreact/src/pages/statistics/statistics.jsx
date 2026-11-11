import "./statistics.css";
import { Link } from "react-router-dom";
import { useEffect, useState, useMemo } from "react";
import { getStudentProgress } from "../../api/studentQuestions";

const Statistics = () => {
    const [period, setPeriod] = useState("all"); // '7d', '30d', 'all'
    const [progressData, setProgressData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");

    const fetchStats = async (selectedPeriod) => {
        try {
            setLoading(true);
            setError("");
            const res = await getStudentProgress(selectedPeriod);
            if (res && res.success && res.data) {
                setProgressData(res.data);
            } else {
                setError(res?.message || "No se pudieron obtener las estadísticas.");
            }
        } catch (err) {
            console.error("Error al cargar estadísticas:", err);
            setError(err?.response?.data?.message || "Error al conectar con el servidor de estadísticas.");
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchStats(period);
    }, [period]);

    const summary = progressData?.summary || {
        total_answered: 0,
        correct_count: 0,
        incorrect_count: 0,
        accuracy: 0,
        total_time_seconds: 0,
        streak_days: 0,
    };

    const bySubject = progressData?.by_subject || [];
    const dailyActivity = progressData?.daily_activity || [];
    const academicAnalysis = progressData?.academic_analysis || {};

    const formatTime = (totalSeconds) => {
        if (!totalSeconds) return "0 min";
        const mins = Math.floor(totalSeconds / 60);
        const secs = totalSeconds % 60;
        if (mins === 0) return `${secs} seg`;
        return `${mins} min ${secs > 0 ? `${secs}s` : ""}`;
    };

    // Puntos dinámicos para el gráfico SVG de evolución temporal
    const svgPoints = useMemo(() => {
        if (dailyActivity.length === 0) return "";
        if (dailyActivity.length === 1) {
            const y = 140 - (dailyActivity[0].accuracy / 100) * 110;
            return `0,${y} 400,${y}`;
        }
        const width = 400;
        const step = width / (dailyActivity.length - 1);
        return dailyActivity
            .map((item, idx) => {
                const x = Math.round(idx * step);
                const y = Math.round(140 - (item.accuracy / 100) * 110);
                return `${x},${y}`;
            })
            .join(" ");
    }, [dailyActivity]);

    return (
        <div className="main__content">
            {/* ENCABEZADO Y FILTROS */}
            <section className="page-header" style={{ alignItems: "flex-start", flexWrap: "wrap", gap: "16px" }}>
                <div className="page-header__info">
                    <h1>Estadísticas y Rendimiento Académico</h1>
                    <p>Métricas oficiales calculadas a partir de tus respuestas en preguntas reales del cuadernillo ICFES.</p>
                </div>

                <div style={{ display: "flex", gap: "8px", background: "rgba(15, 23, 42, 0.6)", padding: "4px", borderRadius: "10px", border: "1px solid rgba(148, 163, 184, 0.2)" }}>
                    <button
                        type="button"
                        onClick={() => setPeriod("7d")}
                        style={{
                            padding: "6px 14px",
                            borderRadius: "7px",
                            border: "none",
                            background: period === "7d" ? "#0284c7" : "transparent",
                            color: period === "7d" ? "#ffffff" : "#94a3b8",
                            fontWeight: 600,
                            fontSize: "12px",
                            cursor: "pointer",
                            transition: "all 0.2s",
                        }}
                    >
                        Últimos 7 días
                    </button>
                    <button
                        type="button"
                        onClick={() => setPeriod("30d")}
                        style={{
                            padding: "6px 14px",
                            borderRadius: "7px",
                            border: "none",
                            background: period === "30d" ? "#0284c7" : "transparent",
                            color: period === "30d" ? "#ffffff" : "#94a3b8",
                            fontWeight: 600,
                            fontSize: "12px",
                            cursor: "pointer",
                            transition: "all 0.2s",
                        }}
                    >
                        Últimos 30 días
                    </button>
                    <button
                        type="button"
                        onClick={() => setPeriod("all")}
                        style={{
                            padding: "6px 14px",
                            borderRadius: "7px",
                            border: "none",
                            background: period === "all" ? "#0284c7" : "transparent",
                            color: period === "all" ? "#ffffff" : "#94a3b8",
                            fontWeight: 600,
                            fontSize: "12px",
                            cursor: "pointer",
                            transition: "all 0.2s",
                        }}
                    >
                        Todo el Historial
                    </button>
                </div>
            </section>

            {/* ESTADO DE CARGA */}
            {loading && (
                <div style={{ textAlign: "center", padding: "60px 20px" }}>
                    <div style={{ fontSize: "36px", color: "#38bdf8", marginBottom: "16px" }}>
                        <i className="fas fa-spinner fa-spin"></i>
                    </div>
                    <p style={{ color: "#94a3b8" }}>Calculando estadísticas en tiempo real...</p>
                </div>
            )}

            {/* ESTADO DE ERROR */}
            {error && (
                <div style={{ padding: "16px", borderRadius: "12px", background: "rgba(239, 68, 68, 0.12)", border: "1px solid rgba(239, 68, 68, 0.3)", marginBottom: "24px", color: "#f87171" }}>
                    <i className="fas fa-triangle-exclamation" style={{ marginRight: "8px" }}></i>
                    {error}
                </div>
            )}

            {!loading && !error && (
                <>
                    {/* ESTADO VACÍO (SIN ACTIVIDAD EN EL PERÍODO) */}
                    {summary.total_answered === 0 ? (
                        <div style={{
                            textAlign: "center",
                            padding: "50px 20px",
                            background: "rgba(15, 23, 42, 0.6)",
                            border: "1px dashed rgba(148, 163, 184, 0.25)",
                            borderRadius: "16px",
                            margin: "20px 0 30px 0",
                        }}>
                            <div style={{ fontSize: "40px", color: "#94a3b8", marginBottom: "16px" }}>
                                <i className="fas fa-chart-simple"></i>
                            </div>
                            <h2 style={{ color: "#f8fafc", fontSize: "18px", fontWeight: 700 }}>
                                Sin respuestas registradas en este período
                            </h2>
                            <p style={{ color: "#94a3b8", maxWidth: "460px", margin: "8px auto 20px auto", fontSize: "14px" }}>
                                Resuelve preguntas reales de Lectura Crítica en el módulo de Prácticas para activar tus métricas y análisis de efectividad.
                            </p>
                            <Link to="/practices">
                                <button className="btn-primary" style={{ padding: "10px 24px" }}>
                                    <i className="fas fa-play" style={{ marginRight: "8px" }}></i>
                                    Iniciar Práctica ICFES
                                </button>
                            </Link>
                        </div>
                    ) : (
                        <>
                            {/* TARJETAS PRINCIPALES DE RENDIMIENTO */}
                            <section className="cards-row" style={{ gridTemplateColumns: "repeat(auto-fit, minmax(200px, 1fr))", gap: "16px", marginBottom: "28px" }}>
                                {/* Preguntas Respondidas */}
                                <div className="stat-card">
                                    <h4 className="stat-card__label">PREGUNTAS RESUELTAS</h4>
                                    <div className="stat-card__score stat-card__score--medium">{summary.total_answered}</div>
                                    <p className="stat-card__note">Reactivos oficiales contestados</p>
                                </div>

                                {/* Aciertos vs Errores */}
                                <div className="stat-card">
                                    <h4 className="stat-card__label">ACIERTOS / ERRORES</h4>
                                    <div style={{ display: "flex", alignItems: "baseline", gap: "8px", margin: "10px 0" }}>
                                        <span style={{ fontSize: "28px", fontWeight: 800, color: "#4ade80" }}>{summary.correct_count}</span>
                                        <span style={{ color: "#94a3b8", fontSize: "18px" }}>/</span>
                                        <span style={{ fontSize: "24px", fontWeight: 700, color: "#f87171" }}>{summary.incorrect_count}</span>
                                    </div>
                                    <p className="stat-card__positive" style={{ color: summary.accuracy >= 70 ? "#4ade80" : "#38bdf8" }}>
                                        {summary.accuracy}% de efectividad global
                                    </p>
                                </div>

                                {/* Tiempo Total */}
                                <div className="stat-card">
                                    <h4 className="stat-card__label">TIEMPO INVERTIDO</h4>
                                    <div className="stat-card__score stat-card__score--medium">{formatTime(summary.total_time_seconds)}</div>
                                    <p className="stat-card__note">Lectura y análisis de reactivos</p>
                                </div>

                                {/* Racha Real */}
                                <div className="stat-card">
                                    <h4 className="stat-card__label">RACHA DE ESTUDIO</h4>
                                    <div style={{ display: "flex", alignItems: "center", gap: "10px", margin: "10px 0" }}>
                                        <i className="fas fa-fire" style={{ color: summary.streak_days > 0 ? "#f97316" : "#64748b", fontSize: "26px" }}></i>
                                        <span style={{ fontSize: "28px", fontWeight: 800, color: "#f8fafc" }}>
                                            {summary.streak_days} {summary.streak_days === 1 ? "día" : "días"}
                                        </span>
                                    </div>
                                    <p className="stat-card__note">
                                        {summary.streak_days > 0 ? "¡Excelente constancia diaria!" : "Practica hoy para iniciar tu racha"}
                                    </p>
                                </div>
                            </section>

                            {/* GRÁFICOS: EVOLUCIÓN TEMPORAL Y ÁREAS ICFES */}
                            <section className="charts-row" style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(320px, 1fr))", gap: "20px", marginBottom: "30px" }}>
                                {/* Gráfico 1: Evolución del Rendimiento Temporal */}
                                <div className="chart-card">
                                    <h3 className="chart-card__title">
                                        <i className="fas fa-chart-line" style={{ marginRight: "8px", color: "#38bdf8" }}></i>
                                        Evolución de Precisión (%)
                                    </h3>
                                    {dailyActivity.length > 0 ? (
                                        <>
                                            <div className="chart-card__placeholder" style={{ padding: "16px 0" }}>
                                                <svg viewBox="0 0 400 150" className="chart-card__svg" style={{ overflow: "visible" }}>
                                                    <defs>
                                                        <linearGradient id="gradientLine" x1="0%" y1="0%" x2="0%" y2="100%">
                                                            <stop offset="0%" stopColor="#38bdf8" stopOpacity="0.3" />
                                                            <stop offset="100%" stopColor="#0284c7" stopOpacity="0.0" />
                                                        </linearGradient>
                                                    </defs>
                                                    {/* Línea de evolución */}
                                                    <polyline
                                                        points={svgPoints}
                                                        fill="none"
                                                        stroke="#38bdf8"
                                                        strokeWidth="3.5"
                                                        strokeLinecap="round"
                                                        strokeLinejoin="round"
                                                    />
                                                    {/* Puntos en cada fecha */}
                                                    {dailyActivity.map((d, i) => {
                                                        const width = 400;
                                                        const x = dailyActivity.length === 1 ? 200 : Math.round((i * width) / (dailyActivity.length - 1));
                                                        const y = Math.round(140 - (d.accuracy / 100) * 110);
                                                        return (
                                                            <g key={d.date}>
                                                                <circle cx={x} cy={y} r="5" fill="#38bdf8" stroke="#0f172a" strokeWidth="2" />
                                                                <text x={x} y={y - 10} fill="#cbd5e1" fontSize="10" textAnchor="middle" fontWeight="bold">
                                                                    {d.accuracy}%
                                                                </text>
                                                            </g>
                                                        );
                                                    })}
                                                </svg>
                                            </div>
                                            <div className="chart-card__footer" style={{ borderTop: "1px solid rgba(148, 163, 184, 0.15)", paddingTop: "12px", display: "flex", justifyContent: "space-between" }}>
                                                <span className="chart-card__stat">{dailyActivity.length} sesiones registradas</span>
                                                <span className="chart-card__meta">Rango: {period === "7d" ? "7 días" : period === "30d" ? "30 días" : "Histórico"}</span>
                                            </div>
                                        </>
                                    ) : (
                                        <p style={{ color: "#94a3b8", fontSize: "13px", padding: "30px 0", textAlign: "center" }}>
                                            No hay suficientes sesiones en este período.
                                        </p>
                                    )}
                                </div>

                                {/* Gráfico 2: Desempeño por las 5 Áreas Oficiales del ICFES */}
                                <div className="chart-card">
                                    <h3 className="chart-card__title">
                                        <i className="fas fa-layer-group" style={{ marginRight: "8px", color: "#818cf8" }}></i>
                                        Rendimiento por Áreas ICFES
                                    </h3>
                                    <div className="chart-card__bars" style={{ display: "flex", flexDirection: "column", gap: "12px" }}>
                                        {bySubject.map((sub) => {
                                            const isActive = sub.published_in_bank > 0;
                                            return (
                                                <div className="bar-item" key={sub.subject_id}>
                                                    <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "4px" }}>
                                                        <span className="bar-item__label" style={{ fontWeight: 600, color: isActive ? "#f8fafc" : "#94a3b8" }}>
                                                            {sub.subject_name}
                                                        </span>
                                                        <span style={{ fontSize: "12px", color: isActive ? "#38bdf8" : "#64748b" }}>
                                                            {isActive ? `${sub.total_answered} resueltas (${sub.accuracy}%)` : "Próximamente"}
                                                        </span>
                                                    </div>
                                                    <div className="bar-item__track" style={{ background: "rgba(148, 163, 184, 0.12)", height: "10px", borderRadius: "5px", overflow: "hidden" }}>
                                                        <div
                                                            className="bar-item__fill"
                                                            style={{
                                                                width: isActive && sub.total_answered > 0 ? `${sub.accuracy}%` : "0%",
                                                                background: sub.accuracy >= 75 ? "linear-gradient(90deg, #10b981, #34d399)" : "linear-gradient(90deg, #0284c7, #38bdf8)",
                                                                height: "100%",
                                                                transition: "width 0.6s ease",
                                                            }}
                                                        ></div>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            </section>

                            {/* ANÁLISIS ACADÉMICO PEDAGÓGICO */}
                            <section style={{
                                background: "linear-gradient(135deg, rgba(15, 23, 42, 0.95) 0%, rgba(30, 41, 59, 0.85) 100%)",
                                border: "1px solid rgba(56, 189, 248, 0.3)",
                                borderRadius: "16px",
                                padding: "24px",
                                marginBottom: "30px",
                            }}>
                                <h3 style={{ color: "#f8fafc", fontSize: "18px", margin: "0 0 14px 0", display: "flex", alignItems: "center", gap: "8px" }}>
                                    <i className="fas fa-lightbulb" style={{ color: "#facc15" }}></i>
                                    Diagnóstico Académico Personalizado
                                </h3>
                                <p style={{ color: "#cbd5e1", fontSize: "14px", lineHeight: "1.6", marginBottom: "18px" }}>
                                    {academicAnalysis.pedagogical_advice}
                                </p>

                                <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))", gap: "14px" }}>
                                    {academicAnalysis.strongest_area && (
                                        <div style={{ background: "rgba(34, 197, 94, 0.08)", border: "1px solid rgba(34, 197, 94, 0.25)", borderRadius: "10px", padding: "14px" }}>
                                            <span style={{ color: "#4ade80", fontSize: "12px", fontWeight: 700, textTransform: "uppercase", display: "block" }}>
                                                Área con Mayor Dominio
                                            </span>
                                            <strong style={{ color: "#f8fafc", fontSize: "16px", display: "block", marginTop: "4px" }}>
                                                {academicAnalysis.strongest_area.name}
                                            </strong>
                                            <span style={{ color: "#94a3b8", fontSize: "13px" }}>
                                                {academicAnalysis.strongest_area.accuracy}% efectividad ({academicAnalysis.strongest_area.answered} reactivos)
                                            </span>
                                        </div>
                                    )}

                                    {academicAnalysis.needs_practice_area ? (
                                        <div style={{ background: "rgba(239, 68, 68, 0.08)", border: "1px solid rgba(239, 68, 68, 0.25)", borderRadius: "10px", padding: "14px" }}>
                                            <span style={{ color: "#f87171", fontSize: "12px", fontWeight: 700, textTransform: "uppercase", display: "block" }}>
                                                Área a Fortalecer
                                            </span>
                                            <strong style={{ color: "#f8fafc", fontSize: "16px", display: "block", marginTop: "4px" }}>
                                                {academicAnalysis.needs_practice_area.name}
                                            </strong>
                                            <span style={{ color: "#94a3b8", fontSize: "13px" }}>
                                                {academicAnalysis.needs_practice_area.accuracy}% efectividad
                                            </span>
                                        </div>
                                    ) : (
                                        <div style={{ background: "rgba(56, 189, 248, 0.08)", border: "1px solid rgba(56, 189, 248, 0.25)", borderRadius: "10px", padding: "14px" }}>
                                            <span style={{ color: "#38bdf8", fontSize: "12px", fontWeight: 700, textTransform: "uppercase", display: "block" }}>
                                                Próximas Áreas
                                            </span>
                                            <strong style={{ color: "#f8fafc", fontSize: "16px", display: "block", marginTop: "4px" }}>
                                                Matemáticas & Ciencias
                                            </strong>
                                            <span style={{ color: "#94a3b8", fontSize: "13px" }}>
                                                En proceso de calibración docente
                                            </span>
                                        </div>
                                    )}
                                </div>
                            </section>
                        </>
                    )}
                </>
            )}
        </div>
    );
};

export default Statistics;
