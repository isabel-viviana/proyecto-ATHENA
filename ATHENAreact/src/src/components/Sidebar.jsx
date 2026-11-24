import { Link, useLocation } from "react-router-dom";
import "./Sidebar.css";
import logo from "../assets/images/Zorrito_8.png";

const Sidebar = () => {
    const location = useLocation();

    const isActive = (path, startsWith = false) => {
        /* startsWith permite mantener activo todo un sub-ruta (ej: perfil/...) */
        const active = startsWith
            ? location.pathname.startsWith(path)
            : location.pathname === path;


        return active ? "sidebar__link--active" : "";
    };

    return (
        <aside className="sidebar">
            {/* LOGO */}
            <div className="sidebar__logo">
                <img src={logo} alt="Zorrito" />
                <span className="sidebar__brand">ATHENA</span>
            </div>

            {/* NAVIGATION */}
            <nav className="sidebar__nav">
                <Link
                    to="/dashboard"
                    className={`sidebar__link ${isActive("/dashboard")}`}
                >
                    <i className="fas fa-home"></i>
                    <span>Inicio</span>
                </Link>

                <Link
                    to="/mock/config"
                    className={`sidebar__link ${isActive("/mock", true)}`}
                >
                    <i className="fas fa-clipboard-check"></i>
                    <span>Simulacros</span>
                </Link>

                <Link
                    to="/practices"
                    className={`sidebar__link ${isActive("/practices", true)}`}
                >
                    <i className="fas fa-dumbbell"></i>
                    <span>Practicas</span>
                </Link>

                <Link
                    to="/ai-tutor"
                    className={`sidebar__link ${isActive("/ai-tutor")}`}
                >
                    <i className="fas fa-robot"></i>
                    <span>Tutor IA</span>
                </Link>

                <Link
                    to="/statistics"
                    className={`sidebar__link ${isActive("/statistics")}`}
                >
                    <i className="fas fa-chart-pie"></i>
                    <span>Estadisticas</span>
                </Link>

                <Link
                    to="/ranking"
                    className={`sidebar__link ${isActive("/ranking")}`}
                >
                    <i className="fas fa-trophy"></i>
                    <span>Ranking</span>
                </Link>

                <Link
                    to="/profile"
                    className={`sidebar__link ${isActive("/profile", true)}`}
                >
                    <i className="fas fa-user-circle"></i>
                    <span>Perfil</span>
                </Link>

                {/* NOTIFICATIONS */}
                <Link
                    to="/profile/notifications"
                    className={`sidebar__link sidebar__link--notifications ${isActive("/profile/notifications")}`}
                >
                    <i className="fas fa-bell"></i>
                    <span>Notificaciones</span>
                    <span className="sidebar__badge">3</span>
                </Link>
            </nav>
        </aside>
    );
};

export default Sidebar;
