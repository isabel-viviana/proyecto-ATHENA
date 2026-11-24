import "./practice_config.css";
import { Link } from "react-router-dom";
const PracticeConfig = () => {
    /* practica por areas: vista estatica con cards (sin estado) */
    return (
        <>
            <header className="page-header">
                <div className="header-text">
                    <h1>Práctica por Áreas</h1>
                    <p>
                        Mejora tus habilidades enfocándote en temas específicos.
                    </p>
                </div>

                {/* objetivo diario: meta y progreso estimado (ui) */}
                <div className="daily-goal-card">
                    <div className="goal-icon">
                        <i className="fas fa-bullseye"></i>
                    </div>
                    <div className="goal-info">
                        <p>
                            Objetivo Diario:{" "}
                            <span className="goal-count">10/20</span> Preguntas
                        </p>
                        <div className="goal-progress-bg">
                            <div
                                className="goal-progress-fill"
                                style={{ width: "50%" }}
                            ></div>
                        </div>
                    </div>
                </div>
            </header>

            {/* grid de areas (tarjetas de acceso rapido) */}
            <div className="areas-grid">
                {/* area matematicas: entrada rapida a la practica completa */}
                <div className="area-card math-area">
                    <div className="card-top">
                        <div className="area-icon">
                            <i className="fas fa-calculator"></i>
                        </div>
                        <i className="fas fa-chevron-right arrow-detail"></i>
                    </div>

                    <h3>Matemáticas</h3>
                    <p className="description">
                        Álgebra, Geometría, Estadística
                    </p>

                    <div className="mastery-section">
                        <div className="mastery-label">
                            <span>Dominio</span>
                            <span className="percentage">65%</span>
                        </div>

                        <div className="mastery-bar">
                            <div
                                className="fill"
                                style={{ width: "65%" }}
                            ></div>
                        </div>
                    </div>

                    <Link to="/practices-full">
                        <button className="practice-btn">
                            Practicar ahora{" "}
                            <i className="fas fa-chevron-right"></i>
                        </button>
                    </Link>
                </div>

                {/*  Lectura Crítica (Purple)  */}
                <div className="area-card reading-area">
                    <div className="card-top">
                        <div className="area-icon">
                            <i className="fas fa-book-open"></i>
                        </div>
                        <i className="fas fa-chevron-right arrow-detail"></i>
                    </div>

                    <h3>Lectura Crítica</h3>
                    <p className="description">
                        Textos continuos y discontinuos
                    </p>

                    <div className="mastery-section">
                        <div className="mastery-label">
                            <span>Dominio</span>
                            <span className="percentage">40%</span>
                        </div>

                        <div className="mastery-bar">
                            <div
                                className="fill"
                                style={{ width: "40%" }}
                            ></div>
                        </div>
                    </div>

                    <Link to="/practices-full">
                        <button className="practice-btn">
                            Practicar ahora{" "}
                            <i className="fas fa-chevron-right"></i>
                        </button>
                    </Link>
                </div>

                {/*  Sociales (Yellow)  */}
                <div className="area-card social-area">
                    <div className="card-top">
                        <div className="area-icon">
                            <i className="fas fa-globe"></i>
                        </div>
                        <i className="fas fa-chevron-right arrow-detail"></i>
                    </div>

                    <h3>Sociales y Ciudadanas</h3>
                    <p className="description">
                        Historia, Geografía, Competencias
                    </p>

                    <div className="mastery-section">
                        <div className="mastery-label">
                            <span>Dominio</span>
                            <span className="percentage">80%</span>
                        </div>

                        <div className="mastery-bar">
                            <div
                                className="fill"
                                style={{ width: "80%" }}
                            ></div>
                        </div>
                    </div>

                    <Link to="/practices-full">
                        <button className="practice-btn">
                            Practicar ahora{" "}
                            <i className="fas fa-chevron-right"></i>
                        </button>
                    </Link>
                </div>

                {/*  Ciencias (Green)  */}
                <div className="area-card science-area">
                    <div className="card-top">
                        <div className="area-icon">
                            <i className="fas fa-atom"></i>
                        </div>
                        <i className="fas fa-chevron-right arrow-detail"></i>
                    </div>

                    <h3>Ciencias Naturales</h3>
                    <p className="description">Biología, Física, Química</p>

                    <div className="mastery-section">
                        <div className="mastery-label">
                            <span>Dominio</span>
                            <span className="percentage">55%</span>
                        </div>
                        <div className="mastery-bar">
                            <div
                                className="fill"
                                style={{ width: "55%" }}
                            ></div>
                        </div>
                    </div>

                    <Link to="/practices-full">
                        <button className="practice-btn">
                            Practicar ahora{" "}
                            <i className="fas fa-chevron-right"></i>
                        </button>
                    </Link>
                </div>

                {/*  Inglés (Red)  */}
                <div className="area-card english-area">
                    <div className="card-top">
                        <div className="area-icon">
                            <i className="fas fa-comment"></i>
                        </div>
                        <div className="improve-tag">
                            <i className="fas fa-chart-line"></i> Área a mejorar
                        </div>
                        <i className="fas fa-chevron-right arrow-detail"></i>
                    </div>

                    <h3>Inglés</h3>
                    <p className="description">
                        Gramática, Vocabulario, Comprensión
                    </p>

                    <div className="mastery-section">
                        <div className="mastery-label">
                            <span>Dominio</span>
                            <span className="percentage">30%</span>
                        </div>

                        <div className="mastery-bar">
                            <div
                                className="fill"
                                style={{ width: "30%" }}
                            ></div>
                        </div>
                    </div>

                    <Link to="/practices-full">
                        <button className="practice-btn">
                            Practicar ahora{" "}
                            <i className="fas fa-chevron-right"></i>
                        </button>
                    </Link>
                </div>
            </div>
        </>
    );
};

export default PracticeConfig;
