import "./dashboard.css";
import { Link } from "react-router-dom";
import { useEffect } from "react";
import { useShallow } from "zustand/react/shallow";
import useProgressStore from "../../stores/useProgressStore";
import DashboardStatsOverview from "./components/DashboardStatsOverview";
import DashboardRecommended from "./components/DashboardRecommended";
import useAuthStore from "../../stores/useAuthStore";

const Dashboard = () => {
    const user = useAuthStore((state) => state.user);
    // useShallow para suscribirse a los estados asíncronos y acción de useProgressStore
    const { fetchDashboardProgress, loading, error, dashboard } = useProgressStore(
        useShallow((state) => ({
            fetchDashboardProgress: state.fetchDashboardProgress,
            loading: state.loading,
            error: state.error,
            dashboard: state.dashboard,
        }))
    );

    useEffect(() => {
        // Dispara la acción asíncrona de Zustand conectada al endpoint real /api/progress
        fetchDashboardProgress();
    }, [fetchDashboardProgress]);

    if (loading && !dashboard) {
        return (
            <div className="dashboard">
                <div className="card" style={{ textAlign: "center", padding: "60px 20px" }}>
                    <div style={{ fontSize: "36px", color: "#6366f1", marginBottom: "16px" }}>
                        <i className="fas fa-spinner fa-spin"></i>
                    </div>
                    <h2>Cargando tu progreso...</h2>
                    <p style={{ color: "#94a3b8" }}>Estamos consultando tus datos académicos en tiempo real.</p>
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
                        onClick={() => fetchDashboardProgress()}
                        style={{ margin: "0 auto", display: "inline-flex" }}
                    >
                        <i className="fas fa-rotate-right" style={{ marginRight: "8px" }}></i>
                        Reintentar consulta
                    </button>
                </div>
            </div>
        );
    }

    const resumen = dashboard?.resumen_general || {};
    const temasFuertes =
        dashboard?.temas_fuertes ||
        dashboard?.fortalezas ||
        [];
    const temasDebiles =
        dashboard?.temas_debiles ||
        dashboard?.debilidades ||
        [];
    const simulacrosRecientes =
        dashboard?.ultimos_simulacros ||
        dashboard?.simulacros_recientes ||
        dashboard?.historial_reciente ||
        [];

    const porcentajeGlobal = Number(resumen.porcentaje_global_aciertos || 0);
    const totalSimulacros = Number(resumen.total_simulacros_completados || 0);
    const totalPreguntas = Number(resumen.total_preguntas_respondidas || 0);
    const correctas = Number(resumen.total_correctas || 0);
    const incorrectas = Number(resumen.total_incorrectas || 0);
    const omitidas = Number(resumen.total_omitidas || 0);

    return (
        <div className="dashboard">
            {/* HEADER */}
            <header className="header">
                <div className="user-welcome">
                    <h1>¡Hola, {user?.full_name?.split(" ")[0] || "Estudiante"}!</h1>
                    <p>
                        Aquí tienes un resumen de tu progreso académico.
                        {totalSimulacros > 0 ? (
                            <>
                                {" "}
                                Has completado{" "}
                                <span className="highlight">
                                    {totalSimulacros} simulacro
                                    {totalSimulacros !== 1 ? "s" : ""}
                                </span>.
                            </>
                        ) : (
                            <> ¡Comienza tu primer simulacro!</>
                        )}
                    </p>
                </div>

                {/* Subcomponente que consume useProgressStore con useShallow (sin prop drilling) */}
                <DashboardStatsOverview />
            </header>

            {/* GRID */}
            <div className="grid-layout">
                {/* Subcomponente que consume useProgressStore con selector atómico (sin prop drilling) */}
                <DashboardRecommended />

                {/* CHART */}
                <section className="card chart-card">
                    <div className="card-header">
                        <h3>Progreso Académico</h3>
                        <span className="highlight">{porcentajeGlobal}% global</span>
                    </div>

                    <div className="chart-placeholder">
                        <div className="line-chart-mock"></div>
                        <div className="chart-labels">
                            <span>Correctas</span>
                            <span>Incorrectas</span>
                            <span>Omitidas</span>
                        </div>
                    </div>

                    <p className="chart-footer">
                        <i className="fas fa-chart-line"></i>
                        <span className="green">{correctas}</span> correctas ·{" "}
                        <span>{incorrectas}</span> incorrectas ·{" "}
                        <span>{omitidas}</span> omitidas
                    </p>
                </section>

                {/* ACTIVIDAD */}
                <section className="card list-card">
                    <div className="card-header">
                        <h3>
                            <i className="far fa-calendar-alt"></i>
                            Actividad Reciente
                        </h3>
                        <Link to="/mock/history" className="view-all">
                            Ver todas
                            <i className="fas fa-chevron-right"></i>
                        </Link>
                    </div>

                    <div className="activity-list">
                        {simulacrosRecientes.length > 0 ? (
                            simulacrosRecientes
                                .slice(0, 5)
                                .map((simulacro, index) => {
                                    const porcentaje = Number(
                                        simulacro.porcentaje ||
                                        simulacro.percentage ||
                                        0
                                    );

                                    return (
                                        <div
                                            className="activity-item"
                                            key={simulacro.id || index}
                                        >
                                            <div className="item-icon green-bg">
                                                <i className="fas fa-bullseye"></i>
                                            </div>
                                            <div className="item-info">
                                                <h4>
                                                    Simulacro{" "}
                                                    {simulacro.exam_type ||
                                                        simulacro.type ||
                                                        "ICFES"}
                                                </h4>
                                                <p>
                                                    {simulacro.created_at ||
                                                        simulacro.date ||
                                                        "Completado"}
                                                </p>
                                            </div>
                                            <div className="item-stat">
                                                <span
                                                    className={
                                                        porcentaje >= 70
                                                            ? "percent green"
                                                            : "percent"
                                                    }
                                                >
                                                    {porcentaje}%
                                                </span>
                                                <p>Acierto</p>
                                            </div>
                                            <i className="fas fa-chevron-right arrow"></i>
                                        </div>
                                    );
                                })
                        ) : (
                            <div className="activity-item">
                                <div className="item-icon blue-bg">
                                    <i className="fas fa-info"></i>
                                </div>
                                <div className="item-info">
                                    <h4>Aún no tienes simulacros</h4>
                                    <p>Realiza tu primer simulacro</p>
                                </div>
                            </div>
                        )}
                    </div>
                </section>

                {/* DESAFÍOS */}
                <section className="card list-card">
                    <div className="card-header">
                        <h3>
                            <i className="fas fa-trophy"></i>
                            Resumen Académico
                        </h3>
                    </div>

                    <div className="challenge-list">
                        <div className="challenge-item-column">
                            <div className="challenge-info">
                                <span>Preguntas respondidas</span>
                                <span>{totalPreguntas}</span>
                            </div>
                            <div className="progress-bar">
                                <div
                                    className="progress"
                                    style={{
                                        width:
                                            totalPreguntas > 0 ? "100%" : "0%",
                                    }}
                                ></div>
                            </div>
                        </div>

                        <div className="challenge-item-column">
                            <div className="challenge-info">
                                <span>Temas fuertes</span>
                                <span className="green">
                                    {temasFuertes.length}
                                    <i className="fas fa-check-circle" style={{ marginLeft: "4px" }}></i>
                                </span>
                            </div>
                            <div className="progress-bar">
                                <div
                                    className="progress progress-green"
                                    style={{
                                        width:
                                            temasFuertes.length > 0
                                                ? "100%"
                                                : "0%",
                                    }}
                                ></div>
                            </div>
                        </div>

                        <div className="challenge-item-column">
                            <div className="challenge-info">
                                <span>Temas por reforzar</span>
                                <span>{temasDebiles.length}</span>
                            </div>
                            <div className="progress-bar">
                                <div
                                    className="progress"
                                    style={{
                                        width:
                                            temasDebiles.length > 0
                                                ? "70%"
                                                : "0%",
                                    }}
                                ></div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    );
};

export default Dashboard;