import "./statistics.css";
import { Link } from "react-router-dom";
import { useEffect, useState } from "react";
import useProgressStore from "../../stores/useProgressStore";
import { getStudentProgress } from "../../api/studentQuestions";

const Statistics = () => {
    const fetchDashboardProgress = useProgressStore((state) => state.fetchDashboardProgress);
    const dashboard = useProgressStore((state) => state.dashboard);
    const totalSimulacros = useProgressStore(
        (state) => state.dashboard?.resumen_general?.total_simulacros_completados ?? 12
    );
    const areaFuerte = useProgressStore(
        (state) => state.dashboard?.temas_fuertes?.[0]?.subject_name || "Lectura Crítica"
    );

    const [qbProgress, setQbProgress] = useState(null);
    const [loadingQb, setLoadingQb] = useState(false);

    useEffect(() => {
        if (!dashboard) {
            fetchDashboardProgress();
        }

        const loadQbStats = async () => {
            try {
                setLoadingQb(true);
                const res = await getStudentProgress();
                if (res && res.success && res.data) {
                    setQbProgress(res.data);
                }
            } catch (err) {
                console.error("Error al cargar progreso del banco de preguntas:", err);
            } finally {
                setLoadingQb(false);
            }
        };

        loadQbStats();
    }, [dashboard, fetchDashboardProgress]);

    return (
        <>
            {/*  CONTENIDO  */}
            <div className="main__content">
                {/*  HEADER  */}
                <section className="page-header">
                    <div className="page-header__info">
                        <h1>Estadísticas y Progreso</h1>
                        <p>
                            Analiza tu rendimiento y sigue mejorando cada día.
                        </p>
                    </div>
                    <Link to="/analysis" className="btn-primary">
                        <i className="fas fa-chart-bar"></i>
                        Ver Análisis Profundo
                    </Link>
                </section>

                {/*  TOP CARDS  */}
                <section className="cards-row">
                    {/*  Puntaje Estimado  */}
                    <div className="stat-card stat-card--large">
                        <h4 className="stat-card__label">
                            PUNTAJE ESTIMADO ACTUAL
                        </h4>
                        <div className="stat-card__score">340</div>
                        <div className="stat-card__tags">
                            <span className="badge badge--purple">
                                Nivel Alto
                            </span>
                            <span className="badge badge--dark">
                                Percentil 78%
                            </span>
                        </div>
                        <p className="stat-card__positive">
                            +25 puntos este mes
                        </p>
                        <p className="stat-card__note">
                            Excelente trabajo, vas por buen camino.
                        </p>
                    </div>

                    {/*  Simulacros  */}
                    <div className="stat-card">
                        <h4 className="stat-card__label">
                            SIMULACROS COMPLETADOS
                        </h4>
                        <div className="stat-card__score stat-card__score--medium">
                            {totalSimulacros}
                        </div>
                        <p className="stat-card__note">Último hace 2 días</p>
                        <p className="stat-card__positive">
                            +3 simulacros este mes
                        </p>
                    </div>

                    {/*  Área más fuerte  */}
                    <div className="stat-card">
                        <h4 className="stat-card__label">ÁREA MÁS FUERTE</h4>
                        <h3 className="stat-card__area">{areaFuerte}</h3>
                        <p className="stat-card__note">Percentil 85%</p>
                        <p className="stat-card__positive">¡Sigue así!</p>
                    </div>
                </section>

                {/* ENTRENAMIENTO BANCO DE PREGUNTAS ICFES EN TIEMPO REAL */}
                {qbProgress && (
                    <section style={{ marginBottom: "24px" }}>
                        <div style={{
                            background: "linear-gradient(135deg, rgba(15, 23, 42, 0.95) 0%, rgba(30, 41, 59, 0.9) 100%)",
                            border: "1px solid rgba(56, 189, 248, 0.3)",
                            borderRadius: "16px",
                            padding: "24px",
                            boxShadow: "0 10px 30px rgba(0, 0, 0, 0.35)",
                        }}>
                            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "18px", flexWrap: "wrap", gap: "10px" }}>
                                <div>
                                    <h3 style={{ color: "#f8fafc", fontSize: "18px", margin: 0, display: "flex", alignItems: "center", gap: "8px" }}>
                                        <i className="fas fa-bullseye" style={{ color: "#38bdf8" }}></i>
                                        Entrenamiento en Banco Oficial ICFES
                                    </h3>
                                    <p style={{ color: "#94a3b8", fontSize: "13px", margin: "4px 0 0 0" }}>
                                        Métricas consolidadas de respuestas en reactivos reales del cuadernillo.
                                    </p>
                                </div>
                                <Link to="/practices" className="btn-primary" style={{ padding: "8px 16px", fontSize: "13px" }}>
                                    <i className="fas fa-play" style={{ marginRight: "6px" }}></i>
                                    Nueva Sesión de Práctica
                                </Link>
                            </div>

                            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(140px, 1fr))", gap: "14px", marginBottom: "20px" }}>
                                <div style={{ background: "rgba(255, 255, 255, 0.03)", padding: "14px", borderRadius: "10px", border: "1px solid rgba(148, 163, 184, 0.15)", textAlign: "center" }}>
                                    <span style={{ color: "#94a3b8", fontSize: "11px", display: "block" }}>Preguntas Resueltas</span>
                                    <strong style={{ color: "#f8fafc", fontSize: "22px" }}>{qbProgress.total_answered}</strong>
                                </div>
                                <div style={{ background: "rgba(34, 197, 94, 0.08)", padding: "14px", borderRadius: "10px", border: "1px solid rgba(34, 197, 94, 0.25)", textAlign: "center" }}>
                                    <span style={{ color: "#4ade80", fontSize: "11px", display: "block" }}>Respuestas Correctas</span>
                                    <strong style={{ color: "#4ade80", fontSize: "22px" }}>{qbProgress.correct_count}</strong>
                                </div>
                                <div style={{ background: "rgba(239, 68, 68, 0.08)", padding: "14px", borderRadius: "10px", border: "1px solid rgba(239, 68, 68, 0.25)", textAlign: "center" }}>
                                    <span style={{ color: "#f87171", fontSize: "11px", display: "block" }}>Respuestas Incorrectas</span>
                                    <strong style={{ color: "#f87171", fontSize: "22px" }}>{qbProgress.incorrect_count}</strong>
                                </div>
                                <div style={{ background: "rgba(56, 189, 248, 0.08)", padding: "14px", borderRadius: "10px", border: "1px solid rgba(56, 189, 248, 0.25)", textAlign: "center" }}>
                                    <span style={{ color: "#38bdf8", fontSize: "11px", display: "block" }}>Efectividad Global</span>
                                    <strong style={{ color: "#38bdf8", fontSize: "22px" }}>{qbProgress.accuracy}%</strong>
                                </div>
                                <div style={{ background: "rgba(168, 85, 247, 0.08)", padding: "14px", borderRadius: "10px", border: "1px solid rgba(168, 85, 247, 0.25)", textAlign: "center" }}>
                                    <span style={{ color: "#c084fc", fontSize: "11px", display: "block" }}>Tiempo Acumulado</span>
                                    <strong style={{ color: "#c084fc", fontSize: "22px" }}>{Math.round(qbProgress.total_time_seconds / 60)} min</strong>
                                </div>
                            </div>

                            {/* Desglose por materia si existe */}
                            {qbProgress.by_subject && qbProgress.by_subject.length > 0 && (
                                <div style={{ marginTop: "12px", borderTop: "1px solid rgba(148, 163, 184, 0.15)", paddingTop: "14px" }}>
                                    <h4 style={{ color: "#cbd5e1", fontSize: "13px", margin: "0 0 10px 0", textTransform: "uppercase", letterSpacing: "0.5px" }}>
                                        Desempeño por Materia Evaluada
                                    </h4>
                                    <div style={{ display: "flex", flexWrap: "wrap", gap: "10px" }}>
                                        {qbProgress.by_subject.map((sub) => (
                                            <div key={sub.subject_id} style={{
                                                background: "rgba(15, 23, 42, 0.7)",
                                                padding: "8px 14px",
                                                borderRadius: "8px",
                                                border: "1px solid rgba(56, 189, 248, 0.25)",
                                                display: "flex",
                                                alignItems: "center",
                                                gap: "10px",
                                                fontSize: "13px",
                                            }}>
                                                <span style={{ color: "#f8fafc", fontWeight: 600 }}>{sub.subject_name}</span>
                                                <span style={{ color: "#94a3b8" }}>{sub.total_answered} resueltas</span>
                                                <span style={{ color: sub.accuracy >= 70 ? "#4ade80" : sub.accuracy >= 50 ? "#38bdf8" : "#f87171", fontWeight: 700 }}>
                                                    {sub.accuracy}% acierto
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    </section>
                )}

                {/*  GRÁFICOS  */}
                <section className="charts-row">
                    {/*  Evolución  */}
                    <div className="chart-card">
                        <h3 className="chart-card__title">
                            Evolución del Puntaje Global
                        </h3>
                        <div className="chart-card__placeholder">
                            <svg
                                viewBox="0 0 400 150"
                                className="chart-card__svg"
                            >
                                <polyline
                                    points="0,120 80,100 160,110 240,70 320,50 400,30"
                                    fill="none"
                                    stroke="#6366F1"
                                    stroke-width="3"
                                    stroke-linecap="round"
                                />
                                <polyline
                                    points="0,120 80,100 160,110 240,70 320,50 400,30"
                                    fill="url(#gradient)"
                                    stroke="none"
                                    opacity="0.15"
                                />
                                <defs>
                                    <linearGradient
                                        id="gradient"
                                        x1="0%"
                                        y1="0%"
                                        x2="0%"
                                        y2="100%"
                                    >
                                        <stop
                                            offset="0%"
                                            stop-color="#6366F1"
                                        />
                                        <stop
                                            offset="100%"
                                            stop-color="#6366F1"
                                            stop-opacity="0"
                                        />
                                    </linearGradient>
                                </defs>
                            </svg>
                        </div>
                        <div className="chart-card__footer">
                            <span className="chart-card__stat">+65 puntos</span>
                            <span className="chart-card__meta">
                                Meta mensual 340 / 400
                            </span>
                        </div>
                    </div>

                    {/*  Desempeño por Áreas  */}
                    <div className="chart-card">
                        <h3 className="chart-card__title">
                            Desempeño por Áreas (%)
                        </h3>
                        <div className="chart-card__bars">
                            <div className="bar-item">
                                <span className="bar-item__label">Lectura</span>
                                <div className="bar-item__track">
                                    <div
                                        className="bar-item__fill bar-item__fill--purple"
                                        style={{ width: "85%" }}
                                    >
                                        85%
                                    </div>
                                </div>
                            </div>
                            <div className="bar-item">
                                <span className="bar-item__label">
                                    Matemáticas
                                </span>
                                <div className="bar-item__track">
                                    <div
                                        className="bar-item__fill bar-item__fill--blue"
                                        style={{ width: "75%" }}
                                    >
                                        75%
                                    </div>
                                </div>
                            </div>
                            <div className="bar-item">
                                <span className="bar-item__label">
                                    Ciencias
                                </span>
                                <div className="bar-item__track">
                                    <div
                                        className="bar-item__fill bar-item__fill--yellow"
                                        style={{ width: "60%" }}
                                    >
                                        60%
                                    </div>
                                </div>
                            </div>
                            <div className="bar-item">
                                <span className="bar-item__label">
                                    Sociales
                                </span>
                                <div className="bar-item__track">
                                    <div
                                        className="bar-item__fill bar-item__fill--green"
                                        style={{ width: "70%" }}
                                    >
                                        70%
                                    </div>
                                </div>
                            </div>
                            <div className="bar-item">
                                <span className="bar-item__label">Inglés</span>
                                <div className="bar-item__track">
                                    <div
                                        className="bar-item__fill bar-item__fill--red"
                                        style={{ width: "30%" }}
                                    >
                                        30%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/*  BOTTOM  */}
                <section className="bottom-row">
                    {/*  Recomendación  */}
                    <div className="recommend-card">
                        <h3 className="recommend-card__title">
                            Recomendación ATHENA
                        </h3>
                        <p className="recommend-card__text">
                            Inglés es tu área con mayor oportunidad de mejora.
                        </p>

                        <Link
                            to="/practices-full"
                            className="recommend-card__btn"
                        >
                            <i className="fas fa-dumbbell"></i>
                            Practicar Inglés
                        </Link>
                    </div>

                    {/*  Mini stats  */}
                    <div className="mini-stats">
                        <div className="mini-card">
                            <p className="mini-card__label">Racha actual</p>
                            <h2 className="mini-card__value">12 días</h2>
                        </div>
                        <div className="mini-card">
                            <p className="mini-card__label">Mejora semanal</p>
                            <h2 className="mini-card__value">+15 pts</h2>
                        </div>
                        <div className="mini-card">
                            <p className="mini-card__label">Puntaje más alto</p>
                            <h2 className="mini-card__value">340</h2>
                        </div>
                    </div>
                </section>
            </div>
        </>
    );
};

export default Statistics;
