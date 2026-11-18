import "./profile.css";
import { Link } from "react-router-dom";

const Profile = () => {
    return (
        <div className="profile-page">
            {/* DECORATIVE BLURS */}
            <div className="bg-blur bg-blur--one"></div>
            <div className="bg-blur bg-blur--two"></div>

            {/* PROFILE HEADER */}
            <section className="profile-header-card">
                <div className="profile-user">
                    <div className="avatar-container">
                        <div className="avatar-circle">A</div>

                        <span className="level-badge">LVL 15</span>
                    </div>

                    <div className="profile-user__info">
                        <div className="name-row">
                            <h1>Andrés Estudiante</h1>

                            <Link to="/profile/edit">
                                <button className="edit-btn">
                                    <i className="fas fa-pen"></i>
                                </button>
                            </Link>
                        </div>

                        <p className="school-info">
                            Colegio San Juan Bosco • Grado 11
                        </p>

                        {/* XP */}
                        <div className="xp-container">
                            <div className="xp-label">
                                <span>
                                    Nivel <strong>15</strong>
                                </span>

                                <span>
                                    <strong>65%</strong> para el siguiente nivel
                                </span>
                            </div>

                            <div className="xp-bar-bg">
                                <div className="xp-bar-fill"></div>
                            </div>

                            <p className="xp-count">3,250 / 5,000 XP</p>
                        </div>
                    </div>
                </div>

                {/* QUICK STATS */}
                <div className="quick-stats">
                    <div className="quick-card">
                        <div className="quick-card__icon quick-card__icon--gold">
                            <i className="fas fa-coins"></i>
                        </div>

                        <div className="quick-card__content">
                            <span className="quick-card__value">1,250</span>
                            <span className="quick-card__label">Monedas</span>
                        </div>

                        <Link to="/store" className="quick-card__link">
                            Ver tienda
                        </Link>
                    </div>

                    <div className="quick-card">
                        <div className="quick-card__icon quick-card__icon--red">
                            <i className="fas fa-fire"></i>
                        </div>

                        <div className="quick-card__content">
                            <span className="quick-card__value">12</span>
                            <span className="quick-card__label">Racha</span>
                        </div>

                        <span className="quick-card__sub">días seguidos</span>
                    </div>

                    <div className="quick-card">
                        <div className="quick-card__icon quick-card__icon--purple">
                            <i className="fas fa-crown"></i>
                        </div>

                        <div className="quick-card__content">
                            <span className="quick-card__label">Plan</span>
                            <span className="quick-card__value">Premium</span>
                        </div>

                        <span className="status-active">Activo</span>
                    </div>
                </div>
            </section>

            {/* SECTION TITLE */}
            <div className="section-heading">
                <h2>Cuenta</h2>

                <p>Gestiona tu progreso, recompensas y configuraciones.</p>
            </div>

            {/* ACCOUNT GRID */}
            <section className="account-grid">
                <Link to="/profile/achievements">
                    <article className="action-card">
                        <div className="action-card__icon blue-bg">
                            <i className="fas fa-award"></i>
                        </div>

                        <div className="action-card__content">
                            <h3>Mis Logros</h3>

                            <p>
                                Descubre tus logros y recompensas desbloqueadas.
                            </p>

                            <span className="action-card__badge blue">
                                15 desbloqueados
                            </span>
                        </div>

                        <i className="fas fa-chevron-right action-card__arrow"></i>
                    </article>
                </Link>

                <Link to="/mock/history">
                    <article className="action-card">
                        <div className="action-card__icon purple-bg">
                            <i className="fas fa-history"></i>
                        </div>

                        <div className="action-card__content">
                            <h3>Historial de Pruebas</h3>

                            <p>Revisa simulacros y prácticas realizadas.</p>

                            <span className="action-card__badge purple">
                                24 pruebas realizadas
                            </span>
                        </div>

                        <i className="fas fa-chevron-right action-card__arrow"></i>
                    </article>
                </Link>

                <Link to="/store">
                    <article className="action-card">
                        <div className="action-card__icon pink-bg">
                            <i className="fas fa-shopping-bag"></i>
                        </div>

                        <div className="action-card__content">
                            <h3>Tienda y Recompensas</h3>

                            <p>Gasta tus monedas en artículos exclusivos.</p>

                            <span className="action-card__badge pink">
                                Nuevos artículos
                            </span>
                        </div>

                        <i className="fas fa-chevron-right action-card__arrow"></i>
                    </article>
                </Link>

                <Link to="/subscriptions">
                    <article className="action-card">
                        <div className="action-card__icon green-bg">
                            <i className="fas fa-shield-alt"></i>
                        </div>

                        <div className="action-card__content">
                            <h3>Suscripción</h3>

                            <p>Gestiona tu plan y métodos de pago.</p>

                            <span className="action-card__badge green">
                                Premium activo
                            </span>
                        </div>

                        <i className="fas fa-chevron-right action-card__arrow"></i>
                    </article>
                </Link>
            </section>

            {/* BOTTOM GRID */}
            <section className="bottom-grid">
                {/* ACTIVITY */}
                <div className="summary-card">
                    <div className="summary-card__header summary-card__header--blue">
                        <i className="fas fa-chart-line"></i>
                        Actividad reciente
                    </div>

                    <div className="activity-content">
                        <div className="activity-icon">
                            <i className="fas fa-bullseye"></i>
                        </div>

                        <div className="activity-info">
                            <h3>¡Vas muy bien, Andrés! 💪</h3>

                            <p>
                                Tu rendimiento ha mejorado <strong>12%</strong>{" "}
                                esta semana.
                            </p>

                            <Link to="/statistics">
                                <button className="btn-outline">
                                    Ver estadísticas
                                    <i className="fas fa-arrow-right"></i>
                                </button>
                            </Link>
                        </div>

                        <div className="rocket-decor">🚀</div>
                    </div>
                </div>

                {/* ACHIEVEMENT */}
                <div className="summary-card">
                    <div className="summary-card__header">Último logro</div>

                    <div className="achievement-content">
                        <div className="trophy-hex">
                            <i className="fas fa-trophy"></i>
                        </div>

                        <div className="achievement-info">
                            <h3>Constante</h3>

                            <p>Completa 7 días seguidos de práctica.</p>

                            <span className="reward">
                                <i className="fas fa-coins"></i>
                                +50 monedas
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            {/* FOOTER */}
            <footer className="profile-footer">
                <Link to="/login" className="logout-btn">
                    <i className="fas fa-sign-out-alt"></i>
                    Cerrar sesión
                </Link>

                <div className="footer-help">
                    ¿Necesitas ayuda?
                    <Link to="/help">
                        Centro de ayuda
                        <i className="fas fa-arrow-right"></i>
                    </Link>
                </div>
            </footer>

            {/* FLOATING BOT */}
            <div className="assistant-help">
                <div className="assistant-help__message">
                    ¡Sigue así! Estás progresando muy bien.
                </div>

                <div className="assistant-help__avatar">🤖</div>
            </div>
        </div>
    );
};

export default Profile;
