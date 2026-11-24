import "./mock_config.css";
import { Link } from "react-router-dom";
import Zorrito from "../../assets/images/Zorrito2D.png";

const MockConfig = () => {
    /* vista estatica de configuracion del simulacro (checkbox/radio solo lectura) */
    return (
        <section className="mock-config">
            <div className="main__content">
                {/* HEADER */}
                <section className="page-header">
                    <div className="page-header__info">
                        <h1>Configurar Simulacro</h1>
                        <p>
                            Personaliza tu prueba según lo que necesites
                            practicar hoy.
                        </p>
                    </div>
                </section>

                {/* CONFIG GRID */}
                <section className="config-grid">
                    {/* LEFT */}
                    <div className="config-card">
                        <div className="card-title">
                            <i className="fas fa-gear"></i>
                            <h3>Tipo de Prueba</h3>
                        </div>

                        <label className="option option--active">
                            <div className="option__top">
                                <div className="option__left">
                                    <input type="radio" checked readOnly />

                                    <div>
                                        <h4>Simulacro Completo ICFES</h4>
                                        <p>Todas las áreas, 4.5 horas</p>
                                    </div>
                                </div>

                                <span className="recommended">Recomendado</span>
                            </div>
                        </label>

                        <label className="option">
                            <div className="option__left">
                                <input type="radio" readOnly />

                                <div>
                                    <h4>Por Área Específica</h4>
                                    <p>Selecciona una o más áreas</p>
                                </div>
                            </div>
                        </label>

                        <label className="option">
                            <div className="option__left">
                                <input type="radio" readOnly />

                                <div>
                                    <h4>Mini Simulacro</h4>
                                    <p>Mitad de preguntas, 2 horas</p>
                                </div>
                            </div>
                        </label>

                        {/* EXTRA */}
                        <div className="extra-settings">
                            <div className="extra-item">
                                <span>Dificultad</span>

                                <select>
                                    <option>Media</option>
                                    <option>Fácil</option>
                                    <option>Difícil</option>
                                </select>
                            </div>

                            <div className="extra-item">
                                <span>Tiempo Extra</span>

                                <select>
                                    <option>No</option>
                                    <option>+15 min</option>
                                    <option>+30 min</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {/* RIGHT */}
                    <div className="config-card">
                        <div className="card-title">
                            <i className="fas fa-file-lines"></i>
                            <h3>Áreas a evaluar</h3>
                        </div>

                        <div className="subjects">
                            <label className="subject">
                                <input type="checkbox" checked readOnly />
                                <span>Matemáticas</span>
                            </label>

                            <label className="subject">
                                <input type="checkbox" checked readOnly />
                                <span>Lectura Crítica</span>
                            </label>

                            <label className="subject">
                                <input type="checkbox" checked readOnly />
                                <span>Sociales y Ciudadanas</span>
                            </label>

                            <label className="subject">
                                <input type="checkbox" checked readOnly />
                                <span>Ciencias Naturales</span>
                            </label>

                            <label className="subject">
                                <input type="checkbox" checked readOnly />
                                <span>Inglés</span>
                            </label>
                        </div>

                        {/* SUMMARY */}
                        <div className="summary-box">
                            <div className="summary-item">
                                <span>Preguntas estimadas</span>
                                <strong>278</strong>
                            </div>

                            <div className="summary-item">
                                <span>Duración total</span>
                                <strong>4h 30m</strong>
                            </div>
                        </div>
                    </div>
                </section>

                {/* ACTIONS */}
                <section className="actions">
                    <button className="btn btn--secondary">Cancelar</button>

                    <Link to="/mock/exam">
                        <button className="btn btn--primary">
                            Iniciar Prueba
                            <i className="fas fa-play"></i>
                        </button>
                    </Link>
                </section>
            </div>

            {/* ASSISTANT */}
            <div className="assistant-box">
                <div className="assistant-message">
                    ¡Sigue así! Estás haciendo un gran trabajo.
                </div>

                <div className="assistant-avatar">
                    <img src={Zorrito} alt="Zorrito" />
                </div>
            </div>
        </section>
    );
};

export default MockConfig;
