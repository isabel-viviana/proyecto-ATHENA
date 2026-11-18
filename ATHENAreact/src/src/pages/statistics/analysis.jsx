import { Link } from "react-router-dom";
import "./analysis.css";

const Analysis = () => {
    return (
        <>
            {/*  CONTENIDO  */}
            <div className="main__content">
                {/*  TÍTULO  */}
                <section className="section-header">
                    <h2>Análisis Diagnóstico IA</h2>
                </section>

                {/*  DIAGNÓSTICO  */}
                <section className="card card--diagnostic">
                    <div className="card__header">
                        <div className="card__icon">
                            <i className="fas fa-lightbulb"></i>
                        </div>
                        <h3>Diagnóstico ATHENA</h3>
                    </div>
                    <p>
                        Basado en tus últimos 5 simulacros, tienes una excelente
                        base en humanidades. Tu principal oportunidad de mejora
                        está en
                        <strong>Inglés (Tiempos verbales)</strong> y
                        <strong>Matemáticas (Trigonometría).</strong> Si mejoras
                        un 15% en estas áreas, tu puntaje global subirá
                        aproximadamente 25 puntos.
                    </p>
                </section>

                {/*  TEMAS CRÍTICOS  */}
                <section className="critical-topics">
                    <h3 className="critical-topics__title">
                        Temas Críticos <span>(Requieren atención)</span>
                    </h3>

                    <div className="topic-card">
                        <div className="topic-card__info">
                            <h4>Pasado Perfecto</h4>
                            <span>Inglés</span>
                        </div>
                        <div className="topic-card__stat">
                            <span className="badge badge--red">
                                75% de error
                            </span>
                        </div>
                        <Link to="/mock/quick" className="topic-card__btn">
                            Practicar esto{" "}
                            <i className="fas fa-arrow-right"></i>
                        </Link>
                    </div>

                    <div className="topic-card">
                        <div className="topic-card__info">
                            <h4>Funciones Trigonométricas</h4>
                            <span>Matemáticas</span>
                        </div>
                        <div className="topic-card__stat">
                            <span className="badge badge--yellow">
                                60% de error
                            </span>
                        </div>
                        <Link to="/mock/quick" className="topic-card__btn">
                            Practicar esto{" "}
                            <i className="fas fa-arrow-right"></i>
                        </Link>
                    </div>

                    <div className="topic-card">
                        <div className="topic-card__info">
                            <h4>Leyes de Mendel</h4>
                            <span>Ciencias</span>
                        </div>
                        <div className="topic-card__stat">
                            <span className="badge badge--yellow">
                                50% de error
                            </span>
                        </div>
                        <Link to="/mock/quick" className="topic-card__btn">
                            Practicar esto{" "}
                            <i className="fas fa-arrow-right"></i>
                        </Link>
                    </div>
                </section>

                {/*  MENSAJE MOTIVACIONAL  */}
                <div className="motivation">
                    <p>¡Sigue así! Estás haciendo un gran trabajo.</p>
                </div>
            </div>
        </>
    );
};

export default Analysis;
