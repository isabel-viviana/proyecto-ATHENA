import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import "./dashboard_admin.css";
import { getAdminStats, toggleAdminUserStatus } from "../../api/documents";

const DashboardSupport = () => {
    const navigate = useNavigate();
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [feedback, setFeedback] = useState("");

    const loadSupportData = async () => {
        setLoading(true);
        setError("");
        try {
            const res = await getAdminStats();
            if (res?.success && res?.data) {
                setData(res.data);
            }
        } catch (err) {
            setError(
                err?.response?.data?.message ||
                    "No se pudo consultar el estado operativo del panel de soporte."
            );
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadSupportData();
    }, []);

    const users = data?.users || { total: 0, active: 0, suspended: 0, recent_users: [] };
    const questions = data?.questions || { total: 0, with_warnings: 0, by_status: {} };
    const activity = data?.academic_activity || { total_exam_attempts: 0, in_progress_exam_attempts: 0 };
    const supportOverview = data?.support_overview || {
        suspended_accounts: [],
        questions_with_warnings: [],
        documents_summary: { total: 0, completed: 0, failed: 0, recent: [] },
    };
    const canManageUsers = Boolean(data?.permissions?.can_manage_users);

    const handleReactivateUser = async (u) => {
        try {
            const res = await toggleAdminUserStatus(u.id, "Activo");
            setFeedback(res?.message || `Cuenta de ${u.full_name} reactivada exitosamente.`);
            await loadSupportData();
            window.dispatchEvent(new Event("athena-admin-stats-refresh"));
        } catch (err) {
            setError(err?.response?.data?.message || "No tienes permisos para reactivar cuentas directamente.");
        }
    };

    const stats = [
        {
            label: "Cuentas Suspendidas",
            value: loading ? "..." : String(users.suspended ?? 0),
            trend: `${users.active ?? 0} cuentas activas en MySQL`,
            isUp: (users.suspended ?? 0) === 0,
            icon: "fa-solid fa-user-lock",
            iconType: "blue",
        },
        {
            label: "Reactivos con Observaciones",
            value: loading ? "..." : String(questions.with_warnings ?? 0),
            trend: `${questions.by_status?.draft || 0} en borrador pendiente`,
            isUp: (questions.with_warnings ?? 0) === 0,
            icon: "fa-solid fa-triangle-exclamation",
            iconType: "amber",
        },
        {
            label: "Documentos Fuente PDF",
            value: loading
                ? "..."
                : `${supportOverview.documents_summary?.completed || 0}/${supportOverview.documents_summary?.total || 0}`,
            trend:
                (supportOverview.documents_summary?.failed || 0) > 0
                    ? `${supportOverview.documents_summary.failed} con error de extracción`
                    : "Sin fallos de extracción",
            isUp: (supportOverview.documents_summary?.failed || 0) === 0,
            icon: "fa-solid fa-file-pdf",
            iconType: "green",
        },
        {
            label: "Simulacros Registrados",
            value: loading ? "..." : String(activity.total_exam_attempts ?? 0),
            trend: `${activity.in_progress_exam_attempts || 0} en curso actualmente`,
            isUp: true,
            icon: "fa-solid fa-circle-check",
            iconType: "purple",
        },
    ];

    const accountsToShow =
        (supportOverview.suspended_accounts || []).length > 0
            ? supportOverview.suspended_accounts
            : users.recent_users || [];

    return (
        <div className="adm-dashboard">
            <div className="adm-hero-card">
                <div className="adm-hero-info">
                    <span className="adm-hero-badge">
                        <i className="fa-solid fa-headset"></i> Diagnóstico y Soporte Operativo
                    </span>
                    <h1>Panel de Soporte y Verificación</h1>
                    <p>
                        Monitoreo real de cuentas estudiantiles en MySQL, observaciones de auditoría del banco de preguntas y estado de cuadernillos en Supabase.
                    </p>
                </div>
                <div className="adm-hero-actions">
                    <button
                        type="button"
                        className="adm-btn-secondary"
                        onClick={() => navigate("/admin/users")}
                    >
                        <i className="fa-solid fa-users"></i>
                        <span>Directorio de Usuarios</span>
                    </button>
                    <button
                        type="button"
                        className="adm-btn-primary"
                        onClick={loadSupportData}
                    >
                        <i className="fa-solid fa-arrows-rotate"></i>
                        <span>Actualizar Estado</span>
                    </button>
                </div>
            </div>

            {feedback && (
                <div
                    style={{
                        padding: "12px 16px",
                        borderRadius: "10px",
                        background: "rgba(16, 185, 129, 0.14)",
                        border: "1px solid rgba(16, 185, 129, 0.35)",
                        color: "#6ee7b7",
                        marginBottom: "16px",
                    }}
                >
                    <i className="fa-solid fa-circle-check"></i> {feedback}
                </div>
            )}

            {error && (
                <div
                    style={{
                        padding: "12px 16px",
                        borderRadius: "10px",
                        background: "rgba(239, 68, 68, 0.14)",
                        border: "1px solid rgba(239, 68, 68, 0.35)",
                        color: "#fca5a5",
                        marginBottom: "16px",
                    }}
                >
                    <i className="fa-solid fa-triangle-exclamation"></i> {error}
                </div>
            )}

            <div className="adm-stats-grid">
                {stats.map((stat, idx) => (
                    <div key={idx} className="adm-stat-card">
                        <div className="adm-stat-info">
                            <span className="adm-stat-label">{stat.label}</span>
                            <span className="adm-stat-value">{stat.value}</span>
                            <span
                                className={`adm-stat-trend ${
                                    stat.isUp ? "adm-stat-trend--up" : "adm-stat-trend--down"
                                }`}
                            >
                                <i
                                    className={`fa-solid ${
                                        stat.isUp ? "fa-circle-check" : "fa-circle-exclamation"
                                    }`}
                                ></i>
                                {stat.trend}
                            </span>
                        </div>
                        <div className={`adm-stat-icon-wrap adm-stat-icon--${stat.iconType}`}>
                            <i className={stat.icon}></i>
                        </div>
                    </div>
                ))}
            </div>

            <div className="adm-grid-2col">
                <div className="adm-card">
                    <div className="adm-card-header">
                        <h2 className="adm-card-title">
                            <i className="fa-solid fa-user-shield"></i>
                            {(supportOverview.suspended_accounts || []).length > 0
                                ? "Cuentas Suspendidas que Requieren Atención"
                                : "Últimas Cuentas Registradas en MySQL"}
                        </h2>
                        <span className="adm-card-badge">
                            {accountsToShow.length} registro(s)
                        </span>
                    </div>

                    <div className="adm-table-wrap">
                        <table className="adm-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Usuario / Correo</th>
                                    <th>Rol</th>
                                    <th>Estado</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                {loading ? (
                                    <tr>
                                        <td colSpan="5" style={{ textAlign: "center", padding: "24px", color: "#94a3b8" }}>
                                            Consultando directorio en MySQL...
                                        </td>
                                    </tr>
                                ) : accountsToShow.length > 0 ? (
                                    accountsToShow.map((u) => (
                                        <tr key={u.id}>
                                            <td>
                                                <span style={{ fontWeight: 700, color: "#38bdf8", fontSize: "0.78rem" }}>
                                                    #{u.id}
                                                </span>
                                            </td>
                                            <td>
                                                <div style={{ fontWeight: 600, color: "#f8fafc" }}>{u.full_name}</div>
                                                <div style={{ fontSize: "0.74rem", color: "#64748b" }}>{u.email}</div>
                                            </td>
                                            <td>
                                                <span className="adm-badge adm-badge--info">{u.role}</span>
                                            </td>
                                            <td>
                                                <span
                                                    className={`adm-badge ${
                                                        u.status === "Activo"
                                                            ? "adm-badge--success"
                                                            : "adm-badge--danger"
                                                    }`}
                                                >
                                                    {u.status}
                                                </span>
                                            </td>
                                            <td>
                                                {u.status === "Suspendido" && canManageUsers ? (
                                                    <button
                                                        type="button"
                                                        onClick={() => handleReactivateUser(u)}
                                                        style={{
                                                            padding: "4px 10px",
                                                            borderRadius: "6px",
                                                            border: "1px solid rgba(16,185,129,0.4)",
                                                            background: "rgba(16,185,129,0.15)",
                                                            color: "#34d399",
                                                            fontSize: "0.75rem",
                                                            cursor: "pointer",
                                                        }}
                                                    >
                                                        Reactivar
                                                    </button>
                                                ) : (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            navigate(
                                                                `/admin/users?search=${encodeURIComponent(u.email)}`
                                                            )
                                                        }
                                                        style={{
                                                            padding: "4px 10px",
                                                            borderRadius: "6px",
                                                            border: "1px solid rgba(148,163,184,0.25)",
                                                            background: "rgba(30,41,59,0.6)",
                                                            color: "#cbd5e1",
                                                            fontSize: "0.75rem",
                                                            cursor: "pointer",
                                                        }}
                                                    >
                                                        Ver ficha
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan="5" style={{ textAlign: "center", padding: "24px", color: "#94a3b8" }}>
                                            No hay cuentas registradas.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div style={{ display: "flex", flexDirection: "column", gap: "20px" }}>
                    <div className="adm-card">
                        <div className="adm-card-header">
                            <h2 className="adm-card-title">
                                <i className="fa-solid fa-triangle-exclamation"></i>
                                Preguntas con Advertencias de Auditoría
                            </h2>
                            <span className="adm-card-badge">
                                {(supportOverview.questions_with_warnings || []).length} en lista
                            </span>
                        </div>
                        <div className="adm-feed-list">
                            {(supportOverview.questions_with_warnings || []).length > 0 ? (
                                supportOverview.questions_with_warnings.slice(0, 5).map((q) => (
                                    <div
                                        key={q.id}
                                        className="adm-feed-item"
                                        style={{ cursor: "pointer" }}
                                        onClick={() => navigate("/admin/questions")}
                                    >
                                        <div
                                            className="adm-feed-icon"
                                            style={{
                                                backgroundColor: "rgba(245, 158, 11, 0.15)",
                                                color: "#f59e0b",
                                            }}
                                        >
                                            <i className="fa-solid fa-flag"></i>
                                        </div>
                                        <div className="adm-feed-body">
                                            <div
                                                className="adm-feed-title"
                                                style={{
                                                    display: "flex",
                                                    justifyContent: "space-between",
                                                }}
                                            >
                                                <span style={{ color: "#22c55e" }}>
                                                    {q.subject_name} (#{q.order_in_group || String(q.id).slice(0, 6)})
                                                </span>
                                                <span style={{ fontSize: "0.7rem", color: "#64748b" }}>
                                                    {q.status}
                                                </span>
                                            </div>
                                            <div style={{ fontSize: "0.76rem", color: "#cbd5e1" }}>
                                                {q.extraction_warnings ||
                                                    `Clave en estado: ${q.key_status}`}
                                            </div>
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div style={{ padding: "16px", color: "#94a3b8", fontSize: "0.85rem" }}>
                                    Todas las preguntas actuales tienen clave verificada y sin advertencias de extracción.
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="adm-card">
                        <div className="adm-card-header">
                            <h2 className="adm-card-title">
                                <i className="fa-solid fa-wrench"></i>
                                Accesos de Asistencia Técnica
                            </h2>
                        </div>
                        <div className="adm-quick-actions">
                            <button
                                type="button"
                                className="adm-quick-btn"
                                onClick={() => navigate("/admin/users?status=Suspendido")}
                            >
                                <i className="fa-solid fa-user-check"></i>
                                <span className="adm-quick-btn-title">Cuentas Suspendidas</span>
                                <span className="adm-quick-btn-desc">Filtrar y reactivar acceso</span>
                            </button>
                            <button
                                type="button"
                                className="adm-quick-btn"
                                onClick={() => navigate("/admin/questions")}
                            >
                                <i className="fa-solid fa-clipboard-check"></i>
                                <span className="adm-quick-btn-title">Bandeja de Revisión</span>
                                <span className="adm-quick-btn-desc">Inspeccionar reactivos</span>
                            </button>
                            <button
                                type="button"
                                className="adm-quick-btn"
                                onClick={() => navigate("/admin/subjects")}
                            >
                                <i className="fa-solid fa-book-bookmark"></i>
                                <span className="adm-quick-btn-title">Catálogo ICFES</span>
                                <span className="adm-quick-btn-desc">Verificar áreas y temas</span>
                            </button>
                            <button
                                type="button"
                                className="adm-quick-btn"
                                onClick={() => navigate("/admin/exams")}
                            >
                                <i className="fa-solid fa-file-signature"></i>
                                <span className="adm-quick-btn-title">Estado de Simulacros</span>
                                <span className="adm-quick-btn-desc">Ver intentos de estudiantes</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default DashboardSupport;
