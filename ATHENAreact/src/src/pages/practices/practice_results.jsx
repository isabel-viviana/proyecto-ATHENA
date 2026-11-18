import "./practice_results.css";
import { Link } from "react-router-dom";

const PracticeResults = () => {
    return (
        <>
            <section className="success-wrapper">
                <div className="success-card">
                    <div className="success-icon">
                        <i className="fas fa-bullseye"></i>
                    </div>

                    <h1>¡Práctica Completada!</h1>

                    <p>
                        Has sumado <strong>+15 XP</strong> a tu progreso en
                        Matemáticas.
                    </p>

                    <div className="success-stats">
                        <div className="mini-box">
                            <span className="mini-box__label">XP Ganada</span>
                            <h3>+15</h3>
                        </div>

                        <div className="mini-box">
                            <span className="mini-box__label">Precisión</span>
                            <h3>82%</h3>
                        </div>

                        <div className="mini-box">
                            <span className="mini-box__label">Tiempo</span>
                            <h3>14 min</h3>
                        </div>
                    </div>

                    <div className="success-buttons">
                        <Link to="/practices">
                            <button className="btn btn-secondary">
                                Volver a Áreas
                            </button>
                        </Link>

                        <Link to="/practices-full">
                            <button className="btn btn-primary">
                                Continuar Practicando
                            </button>
                        </Link>
                    </div>
                </div>
            </section>
        </>
    );
};

export default PracticeResults;
