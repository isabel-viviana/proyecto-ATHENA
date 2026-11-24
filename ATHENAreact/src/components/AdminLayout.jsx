import { useEffect, useState } from "react";
import { Link, Outlet, useLocation, useNavigate } from "react-router-dom";
import { AnimatePresence, motion } from "framer-motion";
import "./AdminLayout.css";
import logoImg from "../assets/images/Zorrito_8.png";
import useAuthStore, { normalizeUserRole } from "../stores/useAuthStore";
import { getAdminStats } from "../api/documents";

const AdminLayout = () => {
    const location = useLocation();
    const navigate = useNavigate();
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [searchText, setSearchText] = useState("");
    const [alertsOpen, setAlertsOpen] = useState(false);
    const [layoutStats, setLayoutStats] = useState(null);

    const storeUser = useAuthStore((state) => state.user);
    const storeRole = useAuthStore((state) => state.role);
    const logout = useAuthStore((state) => state.logout);

    let storedAdmin = null;
    try {
        storedAdmin = JSON.parse(localStorage.getItem("athena_admin_user") || "null");
    } catch {
        storedAdmin = null;
    }

    const currentUser = storeUser || storedAdmin || {};
    const actualRole = normalizeUserRole(storeRole || currentUser?.role || "admin");
    const isActualSuperAdmin = actualRole === "superadmin";

    const currentRoleTitle = isActualSuperAdmin
        ? "Super Administrador"
        : "Administrador Académico";
    const roleBadge = isActualSuperAdmin ? "SUPER ADMIN" : "ADMIN";
    const activeDashboardPath = isActualSuperAdmin
        ? "/dashboard/superadmin"
        : "/dashboard/admin";

    const fetchHeaderStats = async () => {
        try {
            const res = await getAdminStats();
            if (res?.success && res?.data) {
                setLayoutStats(res.data);
            }
        } catch {
            // Silencioso en caso de carga inicial
        }
    };

    useEffect(() => {
        fetchHeaderStats();
        const onRefresh = () => fetchHeaderStats();
        window.addEventListener("athena-admin-stats-refresh", onRefresh);
        return () => window.removeEventListener("athena-admin-stats-refresh", onRefresh);
    }, [location.pathname]);

    const isUsersSection = location.pathname === "/admin/users";
    const isQuestionsSection = location.pathname === "/admin/questions";
    const isSubjectsSection = location.pathname === "/admin/subjects";
    const isExamsSection = location.pathname === "/admin/exams";
    const isSupportSection =
        location.pathname === "/admin/support" ||
        location.pathname.startsWith("/support");

    const usersBadge =
        layoutStats?.users?.total !== undefined ? String(layoutStats.users.total) : null;
    const questionsBadge =
        layoutStats?.questions?.total !== undefined ? String(layoutStats.questions.total) : null;
    const subjectsBadge =
        layoutStats?.catalog?.total_subjects !== undefined
            ? String(layoutStats.catalog.total_subjects)
            : null;
    const examsBadge =
        layoutStats?.academic_activity?.total_exam_attempts !== undefined
            ? String(layoutStats.academic_activity.total_exam_attempts)
            : null;

    const navItems = [
        {
            label: "Dashboard",
            icon: "fa-solid fa-chart-line",
            path: activeDashboardPath,
            isCurrent: location.pathname.includes("/dashboard"),
        },
        ...(isActualSuperAdmin
            ? [
                  {
                      label: "Usuarios y Roles",
                      icon: "fa-solid fa-users-gear",
                      path: "/admin/users",
                      badge: usersBadge,
                      isCurrent: isUsersSection,
                  },
              ]
            : []),
        {
            label: "Banco ICFES",
            icon: "fa-solid fa-layer-group",
            path: "/admin/questions",
            badge: questionsBadge,
            isCurrent: isQuestionsSection,
        },
        {
            label: "Materias y Temas",
            icon: "fa-solid fa-book-bookmark",
            path: "/admin/subjects",
            badge: subjectsBadge,
            isCurrent: isSubjectsSection,
        },
        {
            label: "Simulacros",
            icon: "fa-solid fa-file-signature",
            path: "/admin/exams",
            badge: examsBadge,
            isCurrent: isExamsSection,
        },
        {
            label: "Soporte y Auditoría",
            icon: "fa-solid fa-headset",
            path: "/admin/support",
            isCurrent: isSupportSection,
        },
    ];

    const systemAlerts = [];
    if ((layoutStats?.questions?.by_status?.draft || 0) > 0) {
        systemAlerts.push({
            id: "draft-questions",
            icon: "fa-solid fa-clipboard-check",
            color: "#f59e0b",
            title: `${layoutStats.questions.by_status.draft} pregunta(s) en borrador`,
            desc: "Pendientes de revisión y aprobación administrativa.",
            path: "/admin/questions",
        });
    }
    if ((layoutStats?.questions?.with_warnings || 0) > 0) {
        systemAlerts.push({
            id: "warning-questions",
            icon: "fa-solid fa-triangle-exclamation",
            color: "#ef4444",
            title: `${layoutStats.questions.with_warnings} reactivo(s) con observaciones`,
            desc: "Revisar advertencias en el módulo de soporte y auditoría.",
            path: "/admin/support",
        });
    }
    if (isActualSuperAdmin && (layoutStats?.users?.suspended || 0) > 0) {
        systemAlerts.push({
            id: "suspended-users",
            icon: "fa-solid fa-user-lock",
            color: "#3b82f6",
            title: `${layoutStats.users.suspended} cuenta(s) bloqueada(s)`,
            desc: "Usuarios con acceso restringido temporalmente.",
            path: "/admin/users",
        });
    }

    const handleGlobalSearch = (e) => {
        e.preventDefault();
        const q = searchText.trim();
        if (!q) return;

        if (isActualSuperAdmin && (isUsersSection || q.includes("@"))) {
            navigate(`/admin/users?search=${encodeURIComponent(q)}`);
        } else {
            navigate(`/admin/questions?search=${encodeURIComponent(q)}`);
        }
    };

    const handleLogout = async () => {
        await logout();
        navigate("/login", { replace: true });
    };

    const displayUserName = currentUser?.full_name || currentUser?.name || "Administrador";
    const displayAvatarChar = (displayUserName[0] || roleBadge[0] || "A").toUpperCase();

    return (
        <div className="admin-layout">
            <div
                className={`admin-sidebar-backdrop ${sidebarOpen ? "admin-sidebar-backdrop--visible" : ""}`}
                onClick={() => setSidebarOpen(false)}
            />

            <aside className={`admin-sidebar ${sidebarOpen ? "admin-sidebar--open" : ""}`}>
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

                <nav className="admin-sidebar__nav">
                    <div className="admin-sidebar__nav-section">Módulos Administrativos</div>

                    {navItems.map((item) => (
                        <Link
                            key={item.label}
                            to={item.path}
                            onClick={() => setSidebarOpen(false)}
                            className={`admin-sidebar__link ${item.isCurrent ? "admin-sidebar__link--active" : ""}`}
                        >
                            <i className={item.icon}></i>
                            <span>{item.label}</span>
                            {item.badge !== null && item.badge !== undefined && (
                                <span className="admin-sidebar__link-badge">{item.badge}</span>
                            )}
                        </Link>
                    ))}
                </nav>

                <div className="admin-sidebar__footer">
                    <div className="admin-sidebar__user">
                        <div className="admin-sidebar__user-avatar">{displayAvatarChar}</div>
                        <div className="admin-sidebar__user-info">
                            <div className="admin-sidebar__user-name" title={currentUser?.email || ""}>
                                {displayUserName}
                            </div>
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

            <div className="admin-main-wrapper">
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
                                <i className="fa-solid fa-shield-halved"></i>{" "}
                                {isActualSuperAdmin ? "Panel SuperAdmin" : "Panel Admin"}
                            </span>
                            <span className="admin-header__crumb-sep">/</span>
                            <span className="admin-header__crumb-current">
                                {isUsersSection
                                    ? "Gestionar Usuarios y Roles"
                                    : isQuestionsSection
                                    ? "Banco ICFES"
                                    : isSubjectsSection
                                    ? "Materias y Temas"
                                    : isExamsSection
                                    ? "Simulacros y Actividad"
                                    : isSupportSection
                                    ? "Soporte y Auditoría"
                                    : currentRoleTitle}
                            </span>
                        </div>
                    </div>

                    <div className="admin-header__right" style={{ position: "relative" }}>
                        <form className="admin-header__search" onSubmit={handleGlobalSearch}>
                            <i
                                className="fa-solid fa-magnifying-glass"
                                style={{ cursor: "pointer" }}
                                onClick={handleGlobalSearch}
                            ></i>
                            <input
                                type="text"
                                value={searchText}
                                onChange={(e) => setSearchText(e.target.value)}
                                placeholder={
                                    isUsersSection
                                        ? "Buscar usuario por nombre o correo..."
                                        : isActualSuperAdmin
                                        ? "Buscar preguntas o usuarios (@correo)..."
                                        : "Buscar preguntas del Banco ICFES..."
                                }
                                className="admin-header__search-input"
                            />
                        </form>

                        <div className="admin-header__role-indicator">
                            <span className="admin-header__status-pulse"></span>
                            <span>{roleBadge}</span>
                        </div>

                        <button
                            type="button"
                            className="admin-header__action-btn"
                            title="Avisos operativos del sistema"
                            onClick={() => setAlertsOpen((prev) => !prev)}
                        >
                            <i className="fa-regular fa-bell"></i>
                            {systemAlerts.length > 0 && <span className="admin-header__badge-dot"></span>}
                        </button>

                        {alertsOpen && (
                            <div
                                style={{
                                    position: "absolute",
                                    top: "52px",
                                    right: "48px",
                                    width: "320px",
                                    background: "#0f172a",
                                    border: "1px solid rgba(148, 163, 184, 0.2)",
                                    borderRadius: "12px",
                                    padding: "14px",
                                    boxShadow: "0 16px 36px rgba(0,0,0,0.45)",
                                    zIndex: 120,
                                }}
                            >
                                <div
                                    style={{
                                        display: "flex",
                                        justifyContent: "space-between",
                                        alignItems: "center",
                                        marginBottom: "10px",
                                        paddingBottom: "8px",
                                        borderBottom: "1px solid rgba(148, 163, 184, 0.12)",
                                    }}
                                >
                                    <span style={{ fontWeight: 700, fontSize: "13px", color: "#f8fafc" }}>
                                        Estado Operativo ({systemAlerts.length})
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => setAlertsOpen(false)}
                                        style={{
                                            background: "transparent",
                                            border: "none",
                                            color: "#94a3b8",
                                            cursor: "pointer",
                                        }}
                                    >
                                        <i className="fa-solid fa-xmark"></i>
                                    </button>
                                </div>

                                {systemAlerts.length === 0 ? (
                                    <div style={{ fontSize: "12.5px", color: "#94a3b8", padding: "8px 0" }}>
                                        Sin alertas pendientes en el sistema.
                                    </div>
                                ) : (
                                    <div style={{ display: "flex", flexDirection: "column", gap: "8px" }}>
                                        {systemAlerts.map((alert) => (
                                            <div
                                                key={alert.id}
                                                onClick={() => {
                                                    setAlertsOpen(false);
                                                    navigate(alert.path);
                                                }}
                                                style={{
                                                    display: "flex",
                                                    gap: "10px",
                                                    padding: "9px 10px",
                                                    borderRadius: "8px",
                                                    background: "rgba(30, 41, 59, 0.65)",
                                                    cursor: "pointer",
                                                    alignItems: "flex-start",
                                                }}
                                            >
                                                <i
                                                    className={alert.icon}
                                                    style={{ color: alert.color, marginTop: "3px" }}
                                                ></i>
                                                <div>
                                                    <div
                                                        style={{
                                                            fontSize: "12.5px",
                                                            fontWeight: 600,
                                                            color: "#f1f5f9",
                                                        }}
                                                    >
                                                        {alert.title}
                                                    </div>
                                                    <div style={{ fontSize: "11.5px", color: "#94a3b8" }}>
                                                        {alert.desc}
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        )}

                        <button
                            type="button"
                            className="admin-header__action-btn"
                            title="Cerrar sesión"
                            onClick={handleLogout}
                        >
                            <i className="fa-solid fa-power-off"></i>
                        </button>
                    </div>
                </header>

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
