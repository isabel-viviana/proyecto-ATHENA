import "./dashboard.css";
import Librito from "../../assets/images/Librito.png";
import { Link } from "react-router-dom";
import { useEffect, useState } from "react";
import { getProgressDashboard } from "../../api/progress";

const Dashboard = () => {
    const [dashboard, setDashboard] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        const loadDashboard = async () => {
            try {
                const response = await getProgressDashboard();

                if (response.success) {
                    setDashboard(response.data);
                } else {
                    setError("No fue posible obtener el progreso.");
                }
            } catch (err) {
                console.error("Error cargando dashboard:", err);
                setError("No se pudo conectar con el servidor.");
            } finally {
                setLoading(false);
            }
        };

        loadDashboard();
    }, []);

    if (loading) {
        return (
            <div className="dashboard">
                <div className="card">
                    <h2>Cargando tu progreso...</h2>
                    <p>Estamos consultando tus datos académicos.</p>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="dashboard">
                <div className="card">
                    <h2>No pudimos cargar tu progreso</h2>
                    <p>{error}</p>
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

    const porcentajeGlobal =
        Number(resumen.porcentaje_global_aciertos || 0);

    const totalSimulacros =
        Number(resumen.total_simulacros_completados || 0);

    const totalPreguntas =
        Number(resumen.total_preguntas_respondidas || 0);

    const correctas =
        Number(resumen.total_correctas || 0);

    const incorrectas =
        Number(resumen.total_incorrectas || 0);

    const omitidas =
        Number(resumen.total_omitidas || 0);

    const mejorResultado =
        Number(resumen.mejor_resultado || 0);

    const temaDebil = temasDebiles[0];

    return (
        <div className="dashboard">

            {/* HEADER */}
            <header className="header">
                <div className="user-welcome">
                    <h1>¡Hola, Estudiante!</h1>

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
                            <>
                                {" "}
                                ¡Comienza tu primer simulacro!
                            </>
                        )}
                    </p>
                </div>

                <div className="stats-overview">

                    <div className="stat-card">
                        <div className="stat-icon fire">
                            <i className="fas fa-fire"></i>
                        </div>

                        <div className="stat-content">
                            <span className="stat-value">
                                {totalSimulacros}
                            </span>

                            <p className="stat-label">
                                SIMULACROS
                            </p>

                            <p className="stat-sub">
                                Completados
                            </p>
                        </div>
                    </div>

                    <div className="stat-card">
                        <div className="stat-icon star">
                            <i className="fas fa-star"></i>
                        </div>

                        <div className="stat-content">
                            <span className="stat-value">
                                {porcentajeGlobal}%
                            </span>

                            <p className="stat-label">
                                ACIERTO GLOBAL
                            </p>

                            <p className="stat-sub green">
                                Mejor: {mejorResultado}%
                            </p>
                        </div>
                    </div>

                </div>
            </header>

            {/* GRID */}
            <div className="grid-layout">

                {/* RECOMENDADO */}
                <section className="card recommended-card">

                    <span className="tag">
                        <i className="fas fa-bolt"></i>
                        RECOMENDADO HOY
                    </span>

                    {temaDebil ? (
                        <>
                            <h2>
                                Refuerza:{" "}
                                {temaDebil.topic_name ||
                                    temaDebil.name ||
                                    "Tema por mejorar"}
                            </h2>

                            <p>
                                Hemos detectado que este tema necesita
                                refuerzo. Practica con simulacros para
                                mejorar tu rendimiento.
                            </p>
                        </>
                    ) : (
                        <>
                            <h2>
                                Comienza a medir tu progreso
                            </h2>

                            <p>
                                Realiza un simulacro para que ATHENA
                                pueda analizar tus fortalezas y temas
                                por mejorar.
                            </p>
                        </>
                    )}

                    <Link
                        to="/mock/quick"
                        className="btn-link"
                    >
                        <button className="btn-cta">
                            Comenzar ahora
                            <i className="fas fa-arrow-right"></i>
                        </button>
                    </Link>

                    <div className="mascot-book">
                        <img
                            src={Librito}
                            alt="Libro 3D"
                        />
                    </div>

                </section>

                {/* CHART */}
                <section className="card chart-card">

                    <div className="card-header">
                        <h3>Progreso Académico</h3>

                        <span className="highlight">
                            {porcentajeGlobal}% global
                        </span>
                    </div>

                    <div className="chart-placeholder">

                        <div className="line-chart-mock"></div>

                        <div className="chart-labels">
                            <span>
                                Correctas
                            </span>

                            <span>
                                Incorrectas
                            </span>

                            <span>
                                Omitidas
                            </span>
                        </div>

                    </div>

                    <p className="chart-footer">

                        <i className="fas fa-chart-line"></i>

                        <span className="green">
                            {correctas}
                        </span>{" "}
                        correctas ·{" "}

                        <span>
                            {incorrectas}
                        </span>{" "}
                        incorrectas ·{" "}

                        <span>
                            {omitidas}
                        </span>{" "}
                        omitidas

                    </p>

                </section>

                {/* ACTIVIDAD */}
                <section className="card list-card">

                    <div className="card-header">

                        <h3>
                            <i className="far fa-calendar-alt"></i>
                            Actividad Reciente
                        </h3>

                        <Link
                            to="/mock/history"
                            className="view-all"
                        >
                            Ver todas
                            <i className="fas fa-chevron-right"></i>
                        </Link>

                    </div>

                    <div className="activity-list">

                        {simulacrosRecientes.length > 0 ? (

                            simulacrosRecientes
                                .slice(0, 5)
                                .map((simulacro, index) => {

                                    const porcentaje =
                                        Number(
                                            simulacro.porcentaje ||
                                            simulacro.percentage ||
                                            0
                                        );

                                    return (
                                        <div
                                            className="activity-item"
                                            key={
                                                simulacro.id ||
                                                index
                                            }
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

                                                <p>
                                                    Acierto
                                                </p>

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

                                    <h4>
                                        Aún no tienes simulacros
                                    </h4>

                                    <p>
                                        Realiza tu primer simulacro
                                    </p>

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

                                <span>
                                    Preguntas respondidas
                                </span>

                                <span>
                                    {totalPreguntas}
                                </span>

                            </div>

                            <div className="progress-bar">

                                <div
                                    className="progress"
                                    style={{
                                        width:
                                            totalPreguntas > 0
                                                ? "100%"
                                                : "0%",
                                    }}
                                ></div>

                            </div>

                        </div>

                        <div className="challenge-item-column">

                            <div className="challenge-info">

                                <span>
                                    Temas fuertes
                                </span>

                                <span className="green">
                                    {temasFuertes.length}
                                    <i className="fas fa-check-circle"></i>
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

                                <span>
                                    Temas por reforzar
                                </span>

                                <span>
                                    {temasDebiles.length}
                                </span>

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