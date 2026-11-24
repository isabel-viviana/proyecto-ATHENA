import "./dashboard.css";
import Librito from "../../assets/images/Librito.png";
import { Link } from "react-router-dom";

const Dashboard = () => {
    return (
        <div className="dashboard">
            {/* HEADER */}
            <header className="header">
                <div className="user-welcome">
                    <h1>¡Hola, Estudiante!</h1>
                    <p>
                        Tu objetivo:
                        <span className="highlight"> 350 puntos</span>. Estás a
                        45 puntos de alcanzarlo.
                    </p>
                </div>

                <div className="stats-overview">
                    <div className="stat-card">
                        <div className="stat-icon fire">
                            <i className="fas fa-fire"></i>
                        </div>

                        <div className="stat-content">
                            <span className="stat-value">12</span>
                            <p className="stat-label">RACHA</p>
                            <p className="stat-sub">¡Sigue así!</p>
                        </div>
                    </div>

                    <div className="stat-card">
                        <div className="stat-icon star">
                            <i className="fas fa-star"></i>
                        </div>

                        <div className="stat-content">
                            <span className="stat-value">305</span>
                            <p className="stat-label">PUNTAJE EST.</p>
                            <p className="stat-sub green">+35 esta semana</p>
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

                    <h2>Simulacro Rápido: Lectura Crítica</h2>
                    <p>
                        Hemos detectado que necesitas reforzar textos
                        filosóficos. 15 minutos te ayudarán a subir
                        <span className="blue-text"> 5 puntos estimados</span>.
                    </p>

                    <Link to="/mock/quick" className="btn-link">
                        <button className="btn-cta">
                            Comenzar ahora
                            <i className="fas fa-arrow-right"></i>
                        </button>
                    </Link>

                    <div className="mascot-book">
                        <img src={Librito} alt="Libro 3D" />
                    </div>
                </section>

                {/* CHART */}
                <section className="card chart-card">
                    <div className="card-header">
                        <h3>Progreso Semanal</h3>
                        <select className="selector-week">
                            <option>Esta semana</option>
                        </select>
                    </div>

                    <div className="chart-placeholder">
                        <div className="line-chart-mock"></div>

                        <div className="chart-labels">
                            <span>Lun</span>
                            <span>Mar</span>
                            <span>Mié</span>
                            <span>Jue</span>
                            <span>Vie</span>
                            <span>Sáb</span>
                            <span className="active">Hoy</span>
                        </div>
                    </div>

                    <p className="chart-footer">
                        <i className="fas fa-chart-line"></i>
                        <span className="green">+35 puntos</span>
                        vs. semana pasada
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
                        <div className="activity-item">
                            <div className="item-icon green-bg">
                                <i className="fas fa-bullseye"></i>
                            </div>

                            <div className="item-info">
                                <h4>Práctica: Matemáticas</h4>
                                <p>Hoy, 10:30 AM</p>
                            </div>

                            <div className="item-stat">
                                <span className="percent green">80%</span>
                                <p>Acierto</p>
                            </div>

                            <i className="fas fa-chevron-right arrow"></i>
                        </div>

                        <div className="activity-item">
                            <div className="item-icon blue-bg">
                                <i className="fas fa-comment"></i>
                            </div>

                            <div className="item-info">
                                <h4>Chat con Tutor IA</h4>
                                <p>Ayer, 8:15 PM</p>
                            </div>

                            <span className="badge-tag">Resolución dudas</span>

                            <i className="fas fa-chevron-right arrow"></i>
                        </div>
                    </div>
                </section>

                {/* DESAFÍOS */}
                <section className="card list-card">
                    <div className="card-header">
                        <h3>
                            <i className="fas fa-trophy"></i>
                            Desafíos Semanales
                        </h3>

                        <Link to="/challenges" className="view-all">
                            Ver todos
                            <i className="fas fa-chevron-right"></i>
                        </Link>
                    </div>

                    <div className="challenge-list">
                        <div className="challenge-item-column">
                            <div className="challenge-info">
                                <span>Completar 3 simulacros</span>
                                <span>2 / 3</span>
                            </div>

                            <div className="progress-bar">
                                <div
                                    className="progress"
                                    style={{ width: "66%" }}
                                ></div>
                            </div>
                        </div>

                        <div className="challenge-item-column">
                            <div className="challenge-info">
                                <span>Mantener racha de 5 días</span>

                                <span className="green">
                                    5 / 5<i className="fas fa-check-circle"></i>
                                </span>
                            </div>

                            <div className="progress-bar">
                                <div
                                    className="progress progress-green"
                                    style={{ width: "100%" }}
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
