import "./mock_results.css";
import { Link } from "react-router-dom";

const MockResults = () => {
    /* vista estatica de resultados globales y por area (sin fetch) */
    return (

        <>
            <div className="main__content">
                {/* resultado global: puntuacion y lectura rapida del rendimiento */}
                <section className="result-card">
                    <h1>¡Simulacro Finalizado!</h1>
                    <p>Aquí están tus resultados globales.</p>

                    <div className="score-circle">
                        <div className="score-circle__inner">
                            <h2>315</h2>
                            <span>Puntos ICFES</span>
                        </div>
                    </div>
                </section>

                {/*  stats: desglose general (donut + leyenda) */}
                <section className="stats-grid">
                    {/*  DESEMPEÑO  */}
                    <div className="card">
                        <h3>
                            <i className="fas fa-chart-column"></i>
                            Desempeño General
                        </h3>

                        <div className="donut-chart">
                            <svg viewBox="0 0 42 42">
                                {/*  fondo  */}
                                <circle
                                    cx="21"
                                    cy="21"
                                    r="15.915"
                                    fill="transparent"
                                    stroke="#1E293B"
                                    stroke-width="3"
                                />

                                {/*  correctas  */}
                                <circle
                                    cx="21"
                                    cy="21"
                                    r="15.915"
                                    fill="transparent"
                                    stroke="#22C55E"
                                    stroke-width="3"
                                    stroke-dasharray="68 32"
                                    stroke-linecap="round"
                                    transform="rotate(-90 21 21)"
                                />

                                {/*  incorrectas  */}
                                <circle
                                    cx="21"
                                    cy="21"
                                    r="15.915"
                                    fill="transparent"
                                    stroke="#EF4444"
                                    stroke-width="3"
                                    stroke-dasharray="18 82"
                                    stroke-dashoffset="-68"
                                    stroke-linecap="round"
                                    transform="rotate(-90 21 21)"
                                />

                                {/*  omitidas  */}
                                <circle
                                    cx="21"
                                    cy="21"
                                    r="15.915"
                                    fill="transparent"
                                    stroke="#94A3B8"
                                    stroke-width="3"
                                    stroke-dasharray="8 92"
                                    stroke-dashoffset="-86"
                                    stroke-linecap="round"
                                    transform="rotate(-90 21 21)"
                                />
                            </svg>
                        </div>

                        <div className="legend">
                            <div className="legend-item">
                                <span className="dot dot--green"></span>
                                Correctas
                            </div>

                            <div className="legend-item">
                                <span className="dot dot--red"></span>
                                Incorrectas
                            </div>

                            <div className="legend-item">
                                <span className="dot dot--gray"></span>
                                Omitidas
                            </div>
                        </div>
                    </div>

                    {/*  RESULTADOS  */}
                    <div className="card">
                        <h3>Resultados por Área</h3>
                        <div className="subject">
                            <div className="subject__top">
                                <span>Matemáticas</span>
                                <span>68/100</span>
                            </div>

                            <div className="subject__bar">
                                <div
                                    className="subject__fill green"
                                    style={{ width: "68%" }}
                                ></div>
                            </div>
                        </div>

                        <div className="subject">
                            <div className="subject__top">
                                <span>Lectura Crítica</span>
                                <span>45/100</span>
                            </div>

                            <div className="subject__bar">
                                <div
                                    className="subject__fill red"
                                    style={{ width: "45%" }}
                                ></div>
                            </div>
                        </div>

                        <div className="subject">
                            <div className="subject__top">
                                <span>Sociales</span>
                                <span>55/100</span>
                            </div>

                            <div className="subject__bar">
                                <div
                                    className="subject__fill yellow"
                                    style={{ width: "55%" }}
                                ></div>
                            </div>
                        </div>

                        <div className="subject">
                            <div className="subject__top">
                                <span>Ciencias</span>
                                <span>72/100</span>
                            </div>

                            <div className="subject__bar">
                                <div
                                    className="subject__fill blue"
                                    style={{ width: "72%" }}
                                ></div>
                            </div>
                        </div>
                    </div>
                </section>

                {/*  BOTONES  */}
                <section className="actions">
                    <Link to="/mock/review">
                        <button className="btn btn--secondary">
                            Revisar Respuestas
                        </button>
                    </Link>

                    <Link to="/ai-tutor">
                        <button className="btn btn--primary">
                            Explicación con Tutor IA
                        </button>
                    </Link>
                </section>
            </div>
        </>
    );
};

export default MockResults;
