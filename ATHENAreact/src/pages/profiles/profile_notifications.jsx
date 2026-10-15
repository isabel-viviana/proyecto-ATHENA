import "./profile_notifications.css";
import Zorrito_10 from "../../assets/images/Zorrito_10.png";

const ProfileNotifications = () => {
    return (
        <>
            <header className="page-header">
                <div className="header-left">
                    <i className="fas fa-bell header-icon"></i>
                    <h1>Notificaciones</h1>
                </div>
                <a href="#" className="mark-read">
                    Marcar todo como leído{" "}
                    <i className="fas fa-check-double"></i>
                </a>
            </header>

            <div className="notifications-wrapper">
                <section className="notif-group">
                    <h2 className="group-title">Hoy</h2>

                    <div className="notif-card blue">
                        <span className="status-dot"></span>
                        <div className="icon-box">
                            <i className="fas fa-file-alt"></i>
                        </div>
                        <div className="notif-info">
                            <h3>Nuevo simulacro disponible</h3>
                            <p>El simulacro nacional #4 ya está abierto.</p>
                            <span className="time">Hace 2h</span>
                        </div>
                    </div>

                    <div className="notif-card red">
                        <span className="status-dot"></span>
                        <div className="icon-box">
                            <i className="fas fa-fire"></i>
                        </div>
                        <div className="notif-info">
                            <h3>Racha en peligro</h3>
                            <p>
                                ¡No olvides practicar hoy para mantener tus 12
                                días!
                            </p>
                            <span className="time">Hace 5h</span>
                        </div>
                    </div>
                </section>

                <section className="notif-group">
                    <h2 className="group-title">Ayer</h2>

                    <div className="notif-card green">
                        <span className="status-dot"></span>
                        <div className="icon-box">
                            <i className="fas fa-trophy"></i>
                        </div>
                        <div className="notif-info">
                            <h3>Logro desbloqueado</h3>
                            <p>Has conseguido "Lector Voraz".</p>
                            <span className="time">Ayer</span>
                        </div>
                    </div>

                    <div className="notif-card yellow">
                        <span className="status-dot"></span>
                        <div className="icon-box">
                            <i className="fas fa-chart-line"></i>
                        </div>
                        <div className="notif-info">
                            <h3>Mejora detectada</h3>
                            <p>Tu puntaje en matemáticas subió un 5%.</p>
                            <span className="time">Ayer</span>
                        </div>
                    </div>
                </section>

                <section className="notif-group">
                    <h2 className="group-title">Esta semana</h2>
                    <div className="notif-card purple">
                        <span className="status-dot"></span>
                        <div className="icon-box">
                            <i className="fas fa-medal"></i>
                        </div>
                        <div className="notif-info">
                            <h3>¡Bien hecho!</h3>
                            <p>Completaste 3 prácticas esta semana.</p>
                            <span className="time">Hace 2 días</span>
                        </div>
                    </div>
                </section>

                <section className="notif-group">
                    <h2 className="group-title">Anteriores</h2>
                    <div className="notif-card gray">
                        <span className="status-dot"></span>
                        <div className="icon-box">
                            <i className="far fa-star"></i>
                        </div>
                        <div className="notif-info">
                            <h3>Plan Premium activado</h3>
                            <p>
                                Tu plan estará activo hasta el 12 de mayo de
                                2026.
                            </p>
                            <span className="time">Hace 5 días</span>
                        </div>
                    </div>
                </section>
            </div>

            <div className="mascot-footer">
                <img
                    src={Zorrito_10}
                    alt="Mascota Athena"
                    className="floating-owl"
                />
            </div>
        </>
    );
};

export default ProfileNotifications;
