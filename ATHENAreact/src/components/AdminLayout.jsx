import { useState } from "react";
import { Link, Outlet, useLocation, useNavigate } from "react-router-dom";
import { AnimatePresence, motion } from "framer-motion";
import "./AdminLayout.css";
import logoImg from "../assets/images/Zorrito_8.png";

const AdminLayout = () => {
    const location = useLocation();
    const navigate = useNavigate();
    const [sidebarOpen, setSidebarOpen] = useState(false);

    // Identificar el rol actual según la ruta
    const isSuper = location.pathname.startsWith("/super");
    const isAdmin = location.pathname.startsWith("/admin");
    const isSupport = location.pathname.startsWith("/support");

    let currentRoleTitle = "Super Administrador";
    let roleBadge = "SUPER ADMIN";
    let activeDashboardPath = "/super/dashboard";

    if (isAdmin) {
        currentRoleTitle = "Administrador Académico";
        roleBadge = "ADMIN";
        activeDashboardPath = "/admin/dashboard";
    } else if (isSupport) {
        currentRoleTitle = "Soporte Técnico";
        roleBadge = "SUPPORT";
        activeDashboardPath = "/support/dashboard";
    }

    const isUsersSection = location.pathname === "/admin/users";
    const isQuestionsSection = location.pathname === "/admin/questions";
    const isSubjectsSection = location.pathname === "/admin/subjects";

    const navItems = [
        { label: "Dashboard", icon: "fa-solid fa-chart-line", path: activeDashboardPath, isCurrent: location.pathname.includes("/dashboard") },
        { label: "Usuarios", icon: "fa-solid fa-users", path: "/admin/users", badge: "10", isCurrent: isUsersSection },
        { label: "Banco ICFES", icon: "fa-solid fa-layer-group", path: "/admin/questions", badge: "5.4k", isCurrent: isQuestionsSection },
        { label: "Materias y Temas", icon: "fa-solid fa-book-bookmark", path: "/admin/subjects", badge: "5", isCurrent: isSubjectsSection },
        { label: "Analítica", icon: "fa-solid fa-chart-pie", path: "#" },
        { label: "Notificaciones", icon: "fa-solid fa-bell", path: "#", badge: "5" },
        { label: "Suscripciones", icon: "fa-solid fa-credit-card", path: "#" },
        { label: "Configuración", icon: "fa-solid fa-sliders", path: "#" },
    ];

    const handleLogout = () => {
        navigate("/admin/login");
    };

    return (
        <div className="admin-layout">
            {/* Backdrop para pantallas móviles */}
            <div
                className={`admin-sidebar-backdrop ${sidebarOpen ? "admin-sidebar-backdrop--visible" : ""}`}
                onClick={() => setSidebarOpen(false)}
            />

            {/* Sidebar Fijo */}
            <aside className={`admin-sidebar ${sidebarOpen ? "admin-sidebar--open" : ""}`}>
                {/* Cabecera del Sidebar */}
                <div className="admin-sidebar__header">
                    <Link to={activeDashboardPath} className="admin-sidebar__brand">
                        <img src={logoImg} alt="ATHENA Logo" className="admin-sidebar__logo" />
                        <div className="admin-sidebar__title-wrap">
                            <span className="admin-sidebar__title">ATHENA</span>
                            <span className="admin-sidebar__badge">{roleBadge}</span>
                        </div>
                    </Link>
                    <button
                        className="admin-sidebar__close-btn"
                        onClick={() => setSidebarOpen(false)}
                        aria-label="Cerrar menú"
                    >
                        <i className="fa-solid fa-xmark"></i>
                    </button>
                </div>

                {/* Switcher de Roles para navegación y prueba de Sprint */}
                <div className="admin-sidebar__roles-box">
                    <div className="admin-sidebar__roles-title">Vistas de Dashboard</div>
                    <div className="admin-sidebar__roles-pills">
                        <Link
                            to="/super/dashboard"
                            className={`admin-sidebar__role-pill ${isSuper ? "admin-sidebar__role-pill--active" : ""}`}
                            onClick={() => setSidebarOpen(false)}
                        >
                            Super
                        </Link>
                        <Link
                            to="/admin/dashboard"
                            className={`admin-sidebar__role-pill ${isAdmin ? "admin-sidebar__role-pill--active" : ""}`}
                            onClick={() => setSidebarOpen(false)}
                        >
                            Admin
                        </Link>
                        <Link
                            to="/support/dashboard"
                            className={`admin-sidebar__role-pill ${isSupport ? "admin-sidebar__role-pill--active" : ""}`}
                            onClick={() => setSidebarOpen(false)}
                        >
                            Soporte
                        </Link>
                    </div>
                </div>

                {/* Navegación del Sidebar */}
                <nav className="admin-sidebar__nav">
                    <div className="admin-sidebar__nav-section">Módulos Administrativos</div>

                    {navItems.map((item) => (
                        <Link
                            key={item.label}
                            to={item.path}
                            onClick={() => {
                                if (item.path !== "#") setSidebarOpen(false);
                            }}
                            className={`admin-sidebar__link ${item.isCurrent ? "admin-sidebar__link--active" : ""}`}
                        >
                            <i className={item.icon}></i>
                            <span>{item.label}</span>
                            {item.badge && (
                                <span className="admin-sidebar__link-badge">{item.badge}</span>
                            )}
                        </Link>
                    ))}
                </nav>

                {/* Perfil del Administrador en Sidebar */}
                <div className="admin-sidebar__footer">
                    <div className="admin-sidebar__user">
                        <div className="admin-sidebar__user-avatar">
                            {roleBadge[0]}
                        </div>
                        <div className="admin-sidebar__user-info">
                            <div className="admin-sidebar__user-name">Administrador</div>
                            <div className="admin-sidebar__user-role">{currentRoleTitle}</div>
                        </div>
                        <button
                            className="admin-sidebar__logout-btn"
                            title="Cerrar sesión"
                            onClick={handleLogout}
                        >
                            <i className="fa-solid fa-arrow-right-from-bracket"></i>
                        </button>
                    </div>
                </div>
            </aside>

            {/* Contenedor Principal */}
            <div className="admin-main-wrapper">
                {/* Header Superior */}
                <header className="admin-header">
                    <div className="admin-header__left">
                        <button
                            className="admin-header__mobile-toggle"
                            onClick={() => setSidebarOpen(true)}
                            aria-label="Abrir menú"
                        >
                            <i className="fa-solid fa-bars"></i>
                        </button>
                        <div className="admin-header__breadcrumb">
                            <span className="admin-header__crumb-root">
                                <i className="fa-solid fa-shield-halved"></i> Panel Admin
                            </span>
                            <span className="admin-header__crumb-sep">/</span>
                            <span className="admin-header__crumb-current">
                                {isUsersSection ? "Gestión de Estudiantes" : isQuestionsSection ? "Banco ICFES" : isSubjectsSection ? "Materias y Temas" : currentRoleTitle}
                            </span>
                        </div>
                    </div>

                    <div className="admin-header__right">
                        <div className="admin-header__search">
                            <i className="fa-solid fa-magnifying-glass"></i>
                            <input
                                type="text"
                                placeholder="Buscar en el panel..."
                                className="admin-header__search-input"
                            />
                        </div>

                        <div className="admin-header__role-indicator">
                            <span className="admin-header__status-pulse"></span>
                            <span>{roleBadge}</span>
                        </div>

                        <button className="admin-header__action-btn" title="Notificaciones del sistema">
                            <i className="fa-regular fa-bell"></i>
                            <span className="admin-header__badge-dot"></span>
                        </button>

                        <button
                            className="admin-header__action-btn"
                            title="Salir al Login Administrativo"
                            onClick={handleLogout}
                        >
                            <i className="fa-solid fa-power-off"></i>
                        </button>
                    </div>
                </header>

                {/* Contenido Dinámico con Transición */}
                <main className="admin-content">
                    <AnimatePresence mode="wait">
                        <motion.div
                            key={location.pathname}
                            initial={{ opacity: 0, y: 14 }}
                            animate={{ opacity: 1, y: 0 }}
                            exit={{ opacity: 0, y: -14 }}
                            transition={{ duration: 0.22, ease: "easeOut" }}
                            style={{ minHeight: "100%" }}
                        >
                            <Outlet />
                        </motion.div>
                    </AnimatePresence>
                </main>
            </div>
        </div>
    );
};

export default AdminLayout;
