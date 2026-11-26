import "./dashboard.css";
import { Link } from "react-router-dom";
import { useEffect, useMemo } from "react";
import { useShallow } from "zustand/react/shallow";
import useProgressStore from "../../stores/useProgressStore";
import DashboardStatsOverview from "./components/DashboardStatsOverview";
import DashboardRecommended from "./components/DashboardRecommended";
import useAuthStore from "../../stores/useAuthStore";

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

const Dashboard = () => {
    const user = useAuthStore((state) => state.user);
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
    const resumen = dashboard?.resumen_general || {};
    const bySubject = dashboard?.by_subject || [];
    const dailyActivity = dashboard?.daily_activity || [];
    const temasFuertes =
        dashboard?.strong_topics ||
        dashboard?.temas_fuertes ||
        [];
    const temasDebiles =
        dashboard?.weak_topics ||
        dashboard?.temas_debiles ||
        [];
    const simulacrosRecientes =
        dashboard?.recent_exams ||
        dashboard?.ultimos_simulacros ||
        [];
    const actividadReciente = dashboard?.recent_activity || [];

    const porcentajeGlobal = Number(
        summary.accuracy ?? resumen.porcentaje_global_aciertos ?? 0
    );
    const totalSimulacros = Number(
        summary.completed_exams_count ?? resumen.total_simulacros_completados ?? 0
    );
    const totalPracticas = Number(
        summary.practice_sessions_count ?? resumen.total_practicas_realizadas ?? 0
    );
    const totalPreguntasPractica = Number(
        summary.practice_questions_answered ?? resumen.total_preguntas_practica ?? 0
    );
    const totalPreguntas = Number(
        summary.total_answered ?? resumen.total_preguntas_respondidas ?? 0
    );
    const correctas = Number(summary.correct_count ?? resumen.total_correctas ?? 0);
    const incorrectas = Number(summary.incorrect_count ?? resumen.total_incorrectas ?? 0);
    const omitidas = Number(summary.omitted_count ?? resumen.total_omitidas ?? 0);

    const totalEvaluadasConOmitidas = correctas + incorrectas + omitidas;
    const pctCorrectas =
        totalEvaluadasConOmitidas > 0
            ? Math.round((correctas / totalEvaluadasConOmitidas) * 100)
            : 0;
    const totalTemasEvaluados = temasFuertes.length + temasDebiles.length;
    const pctTemasFuertes =
        totalTemasEvaluados > 0
            ? Math.round((temasFuertes.length / totalTemasEvaluados) * 100)
            : 0;
    const pctTemasDebiles =
        totalTemasEvaluados > 0
            ? Math.round((temasDebiles.length / totalTemasEvaluados) * 100)
            : 0;

    // Puntos reales para el gráfico SVG de evolución del estudiante
    const svgPoints = useMemo(() => {
        if (!Array.isArray(dailyActivity) || dailyActivity.length === 0) return "";
        if (dailyActivity.length === 1) {
            const y = Math.round(130 - (Number(dailyActivity[0].accuracy || 0) / 100) * 100);
            return `20,${y} 380,${y}`;
        }
        const width = 360;
        const step = width / (dailyActivity.length - 1);
        return dailyActivity
            .map((item, idx) => {
                const x = 20 + Math.round(idx * step);
                const y = Math.round(130 - (Number(item.accuracy || 0) / 100) * 100);
                return `${x},${y}`;
            })
            .join(" ");
    }, [dailyActivity]);

    if (loading && !dashboard) {
        return (
            <div className="dashboard">
                <div className="card" style={{ textAlign: "center", padding: "60px 20px" }}>
                    <div style={{ fontSize: "36px", color: "#6366f1", marginBottom: "16px" }}>
                        <i className="fas fa-spinner fa-spin"></i>
                    </div>
                    <h2>Cargando tu progreso...</h2>
                    <p style={{ color: "#94a3b8" }}>
                        Estamos consultando tus datos académicos en tiempo real desde Question Bank.
                    </p>
                </div>
            </div>
        );
    }

    if (error && !dashboard) {
        return (
            <div className="dashboard">
                <div className="card" style={{ textAlign: "center", padding: "60px 20px" }}>
                    <div style={{ fontSize: "36px", color: "#ef4444", marginBottom: "16px" }}>
                        <i className="fas fa-triangle-exclamation"></i>
                    </div>
                    <h2>No pudimos cargar tu progreso</h2>
                    <p style={{ color: "#fca5a5", marginBottom: "20px" }}>{error}</p>
                    <button
                        type="button"
                        className="btn-cta"
                        onClick={() => fetchDashboardProgress("all")}
                        style={{ margin: "0 auto", display: "inline-flex" }}
                    >
                        <i className="fas fa-rotate-right" style={{ marginRight: "8px" }}></i>
                        Reintentar consulta
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="dashboard">
            {/* HEADER */}
            <header className="header">
                <div className="user-welcome">
                    <h1>¡Hola, {user?.full_name?.split(" ")[0] || "Estudiante"}!</h1>
                    <p>
                        Aquí tienes el estado actualizado de tu progreso académico oficial.
                        {totalSimulacros > 0 || totalPracticas > 0 ? (
                            <>
                                {" "}
                                Llevas{" "}
                                <span className="highlight">
                                    {totalSimulacros}{" "}
                                    {totalSimulacros === 1 ? "simulacro" : "simulacros"}
                                </span>{" "}
                                y{" "}
                                <span className="highlight">
                                    {totalPracticas}{" "}
                                    {totalPracticas === 1 ? "sesión de práctica" : "sesiones de práctica"}
                                </span>
                                .
                            </>
                        ) : (
                            <> ¡Comienza tu primera práctica o simulacro hoy!</>
                        )}
                    </p>
                </div>

                {/* Subcomponente con métricas de Prácticas, Simulacros y Acierto Global */}
                <DashboardStatsOverview />
            </header>

            {/* GRID PRINCIPAL */}
            <div className="grid-layout">
                {/* 1. RECOMENDACIÓN ACADÉMICA BASADA EN DATOS REALES */}
                <DashboardRecommended />

                {/* 2. EVOLUCIÓN DEL ESTUDIANTE Y RENDIMIENTO GENERAL */}
                <section className="card chart-card">
                    <div className="card-header">
                        <h3>
                            <i className="fas fa-chart-line" style={{ color: "#38bdf8" }}></i>
                            Evolución y Rendimiento General
                        </h3>
                        <span className="highlight">{porcentajeGlobal}% global</span>
                    </div>

                    {dailyActivity.length > 0 ? (
                        <div className="chart-real-container" style={{ padding: "8px 0 12px" }}>
                            <svg
                                viewBox="0 0 400 150"
                                style={{ width: "100%", height: "150px", overflow: "visible" }}
                            >
                                <polyline
                                    points={svgPoints}
                                    fill="none"
                                    stroke="#38bdf8"
                                    strokeWidth="3"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                />
                                {dailyActivity.map((d, i) => {
                                    const width = 360;
                                    const x =
                                        dailyActivity.length === 1
                                            ? 200
                                            : 20 + Math.round((i * width) / (dailyActivity.length - 1));
                                    const y = Math.round(130 - (Number(d.accuracy || 0) / 100) * 100);
                                    return (
                                        <g key={d.date || i}>
                                            <circle
                                                cx={x}
                                                cy={y}
                                                r="4.5"
                                                fill="#38bdf8"
                                                stroke="#0f172a"
                                                strokeWidth="2"
                                            />
                                            <text
                                                x={x}
                                                y={y - 9}
                                                fill="#cbd5e1"
                                                fontSize="10"
                                                textAnchor="middle"
                                                fontWeight="bold"
                                            >
                                                {d.accuracy}%
                                            </text>
                                            <text
                                                x={x}
                                                y={146}
                                                fill="#64748b"
                                                fontSize="9"
                                                textAnchor="middle"
                                            >
                                                {String(d.date || "").slice(5)}
                                            </text>
                                        </g>
                                    );
                                })}
                            </svg>
                        </div>
                    ) : (
                        <div
                            style={{
                                padding: "32px 16px",
                                textAlign: "center",
                                background: "rgba(15, 23, 42, 0.5)",
                                borderRadius: "14px",
                                border: "1px dashed rgba(148, 163, 184, 0.2)",
                                marginBottom: "12px",
                            }}
                        >
                            <i
                                className="fas fa-chart-area"
                                style={{ fontSize: "26px", color: "#64748b", marginBottom: "8px" }}
                            ></i>
                            <p style={{ color: "#94a3b8", fontSize: "13.5px", margin: 0 }}>
                                Aún no hay sesiones registradas para graficar tu evolución. Resuelve
                                preguntas en Prácticas o presenta un Simulacro para ver tu curva real de
                                aprendizaje.
                            </p>
                        </div>
                    )}

                    <p className="chart-footer" style={{ flexWrap: "wrap" }}>
                        <i className="fas fa-check-double" style={{ color: "#38bdf8" }}></i>
                        <span className="green">{correctas}</span> correctas ·{" "}
                        <span style={{ color: "#f87171" }}>{incorrectas}</span> incorrectas ·{" "}
                        <span>{omitidas}</span> omitidas en simulacros ·{" "}
                        <strong>{totalPreguntas}</strong> respondidas
                    </p>
                </section>

                {/* 3. RENDIMIENTO POR ÁREA ICFES */}
                <section className="card list-card">
                    <div className="card-header">
                        <h3>
                            <i className="fas fa-layer-group" style={{ color: "#818cf8" }}></i>
                            Rendimiento por Área ICFES
                        </h3>
                        <Link to="/statistics" className="view-all">
                            Estadísticas
                            <i className="fas fa-chevron-right"></i>
                        </Link>
                    </div>

                    <div className="challenge-list">
                        {bySubject.length > 0 ? (
                            bySubject.map((sub) => {
                                const answered = Number(sub.total_answered || 0);
                                const acc = Number(sub.accuracy || 0);
                                const isAvailable = Number(sub.published_in_bank || 0) > 0;

                                return (
                                    <div
                                        className="challenge-item-column"
                                        key={sub.subject_id || sub.subject_name}
                                        style={{ padding: "12px 0" }}
                                    >
                                        <div className="challenge-info" style={{ marginBottom: "6px" }}>
                                            <span style={{ fontWeight: 600 }}>
                                                {sub.subject_name}
                                            </span>
                                            <span
                                                style={{
                                                    fontSize: "0.85rem",
                                                    color:
                                                        answered > 0
                                                            ? acc >= 70
                                                                ? "#4ade80"
                                                                : "#38bdf8"
                                                            : "#64748b",
                                                }}
                                            >
                                                {answered > 0
                                                    ? `${sub.correct_count}/${answered} aciertos (${acc}%)`
                                                    : isAvailable
                                                    ? "Sin respuestas aún (0%)"
                                                    : "Próximamente"}
                                            </span>
                                        </div>
                                        <div className="progress-bar">
                                            <div
                                                className={`progress ${
                                                    acc >= 70 ? "progress-green" : ""
                                                }`}
                                                style={{
                                                    width: answered > 0 ? `${Math.min(100, Math.max(0, acc))}%` : "0%",
                                                    transition: "width 0.4s ease",
                                                }}
                                            ></div>
                                        </div>
                                    </div>
                                );
                            })
                        ) : (
                            <p style={{ color: "#94a3b8", fontSize: "14px", padding: "16px 0" }}>
                                No hay áreas registradas para mostrar en este momento.
                            </p>
                        )}
                    </div>
                </section>

                {/* 4. TEMAS CON DIFICULTADES Y RESUMEN */}
                <section className="card list-card">
                    <div className="card-header">
                        <h3>
                            <i className="fas fa-chart-line" style={{ color: "#facc15" }}></i>
                            Temas con Dificultades y Resumen
                        </h3>
                        <Link to="/statistics" className="view-all">
                            Estadísticas
                            <i className="fas fa-chevron-right"></i>
                        </Link>
                    </div>

                    <div className="challenge-list">
                        <div className="challenge-item-column" style={{ padding: "12px 0" }}>
                            <div className="challenge-info">
                                <span>Efectividad en preguntas respondidas ({totalPreguntas})</span>
                                <span>{pctCorrectas}%</span>
                            </div>
                            <div className="progress-bar">
                                <div
                                    className={`progress ${pctCorrectas >= 70 ? "progress-green" : ""}`}
                                    style={{ width: `${pctCorrectas}%` }}
                                ></div>
                            </div>
                        </div>

                        <div className="challenge-item-column" style={{ padding: "12px 0" }}>
                            <div className="challenge-info">
                                <span>Temas dominados ({temasFuertes.length})</span>
                                <span className="green">
                                    {pctTemasFuertes}%
                                    <i className="fas fa-check-circle" style={{ marginLeft: "4px" }}></i>
                                </span>
                            </div>
                            <div className="progress-bar">
                                <div
                                    className="progress progress-green"
                                    style={{ width: `${pctTemasFuertes}%` }}
                                ></div>
                            </div>
                        </div>

                        <div className="challenge-item-column" style={{ padding: "12px 0" }}>
                            <div className="challenge-info">
                                <span>Temas con dificultades ({temasDebiles.length})</span>
                                <span style={{ color: temasDebiles.length > 0 ? "#f87171" : "#94a3b8" }}>
                                    {pctTemasDebiles}%
                                </span>
                            </div>
                            <div className="progress-bar">
                                <div
                                    className="progress"
                                    style={{
                                        width: `${pctTemasDebiles}%`,
                                        background: temasDebiles.length > 0 ? "#ef4444" : "#38bdf8",
                                    }}
                                ></div>
                            </div>
                        </div>

                        {/* Listado directo de temas con dificultades reales */}
                        {temasDebiles.length > 0 ? (
                            <div style={{ marginTop: "12px" }}>
                                <p
                                    style={{
                                        fontSize: "12px",
                                        color: "#94a3b8",
                                        textTransform: "uppercase",
                                        fontWeight: 700,
                                        marginBottom: "8px",
                                    }}
                                >
                                    Prioridades de estudio detectadas:
                                </p>
                                {temasDebiles.slice(0, 3).map((tema, idx) => (
                                    <div
                                        key={tema.topic_id || tema.topic_name || idx}
                                        style={{
                                            display: "flex",
                                            justifyContent: "space-between",
                                            alignItems: "center",
                                            padding: "8px 12px",
                                            borderRadius: "10px",
                                            background: "rgba(239, 68, 68, 0.08)",
                                            border: "1px solid rgba(239, 68, 68, 0.25)",
                                            marginBottom: "6px",
                                            fontSize: "13px",
                                        }}
                                    >
                                        <div>
                                            <strong style={{ color: "#f8fafc", display: "block" }}>
                                                {tema.topic_name || tema.name}
                                            </strong>
                                            <small style={{ color: "#94a3b8" }}>
                                                {tema.subject_name} · {tema.incorrect_count}/
                                                {tema.total_answered} fallos
                                            </small>
                                        </div>
                                        <span
                                            style={{
                                                color: "#f87171",
                                                fontWeight: 700,
                                                fontSize: "12px",
                                            }}
                                        >
                                            {tema.error_rate ?? Math.round(100 - (tema.accuracy || 0))}% error
                                        </span>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p style={{ color: "#94a3b8", fontSize: "13px", marginTop: "12px" }}>
                                {totalPreguntas > 0
                                    ? "¡Excelente! No tienes temas críticos por debajo del 70% de efectividad."
                                    : "Resuelve prácticas o simulacros para identificar temas con dificultades."}
                            </p>
                        )}
                    </div>
                </section>

                {/* 5. ÚLTIMOS RESULTADOS DE SIMULACROS */}
                <section className="card list-card">
                    <div className="card-header">
                        <h3>
                            <i className="fas fa-clipboard-check" style={{ color: "#4ade80" }}></i>
                            Últimos Resultados (Simulacros)
                        </h3>
                        <Link to="/mock/history" className="view-all">
                            Ver historial
                            <i className="fas fa-chevron-right"></i>
                        </Link>
                    </div>

                    <div className="activity-list">
                        {simulacrosRecientes.length > 0 ? (
                            simulacrosRecientes.slice(0, 5).map((simulacro, index) => {
                                const porcentaje = Number(
                                    simulacro.accuracy ??
                                        simulacro.porcentaje ??
                                        simulacro.percentage ??
                                        0
                                );
                                const score = Number(simulacro.score ?? 0);

                                return (
                                    <Link
                                        to={`/mock/result?id=${simulacro.id}`}
                                        className="activity-item"
                                        key={simulacro.id || index}
                                        style={{ textDecoration: "none", color: "inherit" }}
                                    >
                                        <div className="item-icon green-bg">
                                            <i className="fas fa-bullseye"></i>
                                        </div>
                                        <div className="item-info">
                                            <h4>
                                                {simulacro.title ||
                                                    `Simulacro ${
                                                        simulacro.subject_name ||
                                                        simulacro.exam_type ||
                                                        "ICFES"
                                                    }`}
                                            </h4>
                                            <p>
                                                {formatShortDate(
                                                    simulacro.finished_at || simulacro.created_at
                                                )}{" "}
                                                · {simulacro.questions_count || 0} preguntas · Puntaje:{" "}
                                                {score}
                                            </p>
                                        </div>
                                        <div className="item-stat">
                                            <span
                                                className={
                                                    porcentaje >= 70 ? "percent green" : "percent"
                                                }
                                            >
                                                {porcentaje}%
                                            </span>
                                            <p>Acierto</p>
                                        </div>
                                        <i className="fas fa-chevron-right arrow"></i>
                                    </Link>
                                );
                            })
                        ) : (
                            <div className="activity-item">
                                <div className="item-icon blue-bg">
                                    <i className="fas fa-info"></i>
                                </div>
                                <div className="item-info">
                                    <h4>Aún no tienes simulacros completados</h4>
                                    <p>
                                        Inicia un{" "}
                                        <Link to="/mock/quick" style={{ color: "#38bdf8" }}>
                                            Mini Simulacro Rápido
                                        </Link>{" "}
                                        o configura un simulacro por área.
                                    </p>
                                </div>
                            </div>
                        )}
                    </div>
                </section>

                {/* 6. ÚLTIMAS ACTIVIDADES (RESPUESTAS RECIENTES EN PRÁCTICAS Y SIMULACROS) */}
                <section className="card list-card">
                    <div className="card-header">
                        <h3>
                            <i className="far fa-calendar-alt" style={{ color: "#38bdf8" }}></i>
                            Últimas Actividades ({totalPreguntasPractica} en práctica)
                        </h3>
                        <Link to="/practices" className="view-all">
                            Ir a prácticas
                            <i className="fas fa-chevron-right"></i>
                        </Link>
                    </div>

                    <div className="activity-list">
                        {actividadReciente.length > 0 ? (
                            actividadReciente.slice(0, 5).map((act, index) => {
                                const isCorrect = Boolean(act.is_correct);
                                const isSimulacro = act.practice_mode === "simulacro";

                                return (
                                    <div
                                        className="activity-item"
                                        key={act.answer_id || act.question_id || index}
                                    >
                                        <div
                                            className={`item-icon ${
                                                isCorrect ? "green-bg" : "blue-bg"
                                            }`}
                                            style={
                                                !isCorrect
                                                    ? {
                                                          background: "rgba(239, 68, 68, 0.12)",
                                                          color: "#f87171",
                                                      }
                                                    : undefined
                                            }
                                        >
                                            <i
                                                className={`fas ${
                                                    isCorrect ? "fa-check" : "fa-xmark"
                                                }`}
                                            ></i>
                                        </div>
                                        <div className="item-info">
                                            <h4>
                                                {act.subject_name || "Área ICFES"}{" "}
                                                <span
                                                    style={{
                                                        fontSize: "11px",
                                                        color: "#94a3b8",
                                                        fontWeight: 500,
                                                    }}
                                                >
                                                    · {isSimulacro ? "Simulacro" : "Práctica"}
                                                </span>
                                            </h4>
                                            <p>
                                                {String(act.statement || "Reactivo oficial").slice(
                                                    0,
                                                    70
                                                )}
                                                {String(act.statement || "").length > 70
                                                    ? "..."
                                                    : ""}{" "}
                                                ({formatShortDate(act.answered_at)})
                                            </p>
                                        </div>
                                        <div className="item-stat">
                                            <span
                                                className={
                                                    isCorrect ? "percent green" : "percent"
                                                }
                                                style={
                                                    !isCorrect ? { color: "#f87171" } : undefined
                                                }
                                            >
                                                Opción {act.selected_option_letter || "-"}
                                            </span>
                                            <p>{isCorrect ? "Correcta" : "Incorrecta"}</p>
                                        </div>
                                    </div>
                                );
                            })
                        ) : (
                            <div className="activity-item">
                                <div className="item-icon blue-bg">
                                    <i className="fas fa-book-open"></i>
                                </div>
                                <div className="item-info">
                                    <h4>Sin actividades registradas</h4>
                                    <p>
                                        Resuelve preguntas en{" "}
                                        <Link to="/practices" style={{ color: "#38bdf8" }}>
                                            Prácticas ICFES
                                        </Link>{" "}
                                        para ver tu actividad reciente aquí.
                                    </p>
                                </div>
                            </div>
                        )}
                    </div>
                </section>
            </div>
        </div>
    );
};

export default Dashboard;
