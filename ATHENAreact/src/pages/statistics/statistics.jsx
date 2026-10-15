import "./statistics.css";
import { Link } from "react-router-dom";

const Statistics = () => {
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
                            12
                        </div>
                        <p className="stat-card__note">Último hace 2 días</p>
                        <p className="stat-card__positive">
                            +3 simulacros este mes
                        </p>
                    </div>

                    {/*  Área más fuerte  */}
                    <div className="stat-card">
                        <h4 className="stat-card__label">ÁREA MÁS FUERTE</h4>
                        <h3 className="stat-card__area">Lectura Crítica</h3>
                        <p className="stat-card__note">Percentil 85%</p>
                        <p className="stat-card__positive">¡Sigue así!</p>
                    </div>
                </section>

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
