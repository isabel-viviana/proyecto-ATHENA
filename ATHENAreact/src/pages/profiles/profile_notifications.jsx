import "./profile_notifications.css";
import { useState, useEffect } from "react";
import Zorrito_10 from "../../assets/images/Zorrito_10.png";
import {
    getStudentNotifications,
    markNotificationAsRead,
    markAllNotificationsAsRead,
    deleteStudentNotification,
} from "../../api/studentQuestions";

const ProfileNotifications = () => {
    const [notifications, setNotifications] = useState([]);
    const [filter, setFilter] = useState("all"); // 'all', 'unread', 'read'
    const [unreadCount, setUnreadCount] = useState(0);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [actionMsg, setActionMsg] = useState("");

    const loadNotifications = async (selectedFilter) => {
        try {
            setLoading(true);
            setError("");
            const res = await getStudentNotifications(selectedFilter);
            if (res && res.success) {
                setNotifications(res.data || []);
                setUnreadCount(res.unread_count || 0);
            } else {
                setError(res?.message || "Error al cargar notificaciones.");
            }
        } catch (err) {
            console.error("Error al cargar notificaciones:", err);
            setError(err?.response?.data?.message || "No se pudo conectar con el servidor.");
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadNotifications(filter);
    }, [filter]);

    const handleMarkAsRead = async (id, e) => {
        if (e) e.stopPropagation();
        try {
            await markNotificationAsRead(id);
            setNotifications((prev) =>
                prev.map((n) => (n.id === id ? { ...n, is_read: true } : n))
            );
            setUnreadCount((prev) => Math.max(0, prev - 1));
        } catch (err) {
            console.error("Error al marcar como leída:", err);
        }
    };

    const handleMarkAllRead = async (e) => {
        e.preventDefault();
        try {
            await markAllNotificationsAsRead();
            setNotifications((prev) => prev.map((n) => ({ ...n, is_read: true })));
            setUnreadCount(0);
            setActionMsg("Todas las notificaciones fueron marcadas como leídas.");
            setTimeout(() => setActionMsg(""), 3000);
        } catch (err) {
            console.error("Error al marcar todo como leído:", err);
        }
    };

    const handleDelete = async (id, e) => {
        if (e) e.stopPropagation();
        try {
            await deleteStudentNotification(id);
            const deleted = notifications.find((n) => n.id === id);
            setNotifications((prev) => prev.filter((n) => n.id !== id));
            if (deleted && !deleted.is_read) {
                setUnreadCount((prev) => Math.max(0, prev - 1));
            }
        } catch (err) {
            console.error("Error al eliminar notificación:", err);
        }
    };

    const formatRelativeTime = (isoString) => {
        if (!isoString) return "Reciente";
        const date = new Date(isoString);
        const now = new Date();
        const diffSeconds = Math.floor((now - date) / 1000);

        if (diffSeconds < 60) return "Hace un momento";
        const diffMinutes = Math.floor(diffSeconds / 60);
        if (diffMinutes < 60) return `Hace ${diffMinutes} min`;
        const diffHours = Math.floor(diffMinutes / 60);
        if (diffHours < 24) return `Hace ${diffHours}h`;
        const diffDays = Math.floor(diffHours / 24);
        if (diffDays === 1) return "Ayer";
        if (diffDays < 7) return `Hace ${diffDays} días`;
        return date.toLocaleDateString("es-CO", { day: "numeric", month: "short" });
    };

    const getTypeConfig = (type) => {
        switch (type) {
            case "achievement":
                return { colorClass: "yellow", icon: "fa-trophy", label: "Logro" };
            case "exam":
                return { colorClass: "blue", icon: "fa-file-alt", label: "Práctica / Examen" };
            case "reminder":
                return { colorClass: "red", icon: "fa-clock", label: "Recordatorio" };
            case "premium":
                return { colorClass: "purple", icon: "fa-crown", label: "Novedad" };
            case "ai":
                return { colorClass: "blue", icon: "fa-robot", label: "Tutor IA" };
            case "system":
            default:
                return { colorClass: "gray", icon: "fa-circle-info", label: "Aviso Institucional" };
        }
    };

    return (
        <div style={{ padding: "10px 0" }}>
            {/* ENCABEZADO */}
            <header className="page-header" style={{ marginBottom: "20px", flexWrap: "wrap", gap: "12px" }}>
                <div className="header-left">
                    <i className="fas fa-bell header-icon" style={{ color: "#38bdf8" }}></i>
                    <div>
                        <h1 style={{ color: "#f8fafc", fontSize: "24px", margin: 0 }}>Bandeja de Notificaciones</h1>
                        <span style={{ color: "#94a3b8", fontSize: "13px" }}>
                            {unreadCount > 0 ? `${unreadCount} pendientes sin leer` : "Estás al día con todos tus avisos"}
                        </span>
                    </div>
                </div>

                <div style={{ display: "flex", alignItems: "center", gap: "16px" }}>
                    {unreadCount > 0 && (
                        <button
                            type="button"
                            onClick={handleMarkAllRead}
                            style={{
                                background: "transparent",
                                border: "none",
                                color: "#38bdf8",
                                cursor: "pointer",
                                fontSize: "13px",
                                fontWeight: 600,
                                display: "inline-flex",
                                alignItems: "center",
                                gap: "6px",
                            }}
                        >
                            <i className="fas fa-check-double"></i>
                            Marcar todo como leído
                        </button>
                    )}
                </div>
            </header>

            {actionMsg && (
                <div style={{
                    padding: "10px 16px",
                    background: "rgba(34, 197, 94, 0.15)",
                    border: "1px solid rgba(34, 197, 94, 0.35)",
                    borderRadius: "8px",
                    color: "#4ade80",
                    fontSize: "13px",
                    marginBottom: "16px",
                }}>
                    <i className="fas fa-check" style={{ marginRight: "6px" }}></i>
                    {actionMsg}
                </div>
            )}

            {/* FILTROS */}
            <div style={{
                display: "inline-flex",
                background: "rgba(15, 23, 42, 0.6)",
                border: "1px solid rgba(148, 163, 184, 0.2)",
                padding: "3px",
                borderRadius: "10px",
                marginBottom: "24px",
                gap: "4px",
            }}>
                <button
                    type="button"
                    onClick={() => setFilter("all")}
                    style={{
                        padding: "6px 14px",
                        borderRadius: "7px",
                        border: "none",
                        background: filter === "all" ? "#0284c7" : "transparent",
                        color: filter === "all" ? "#ffffff" : "#94a3b8",
                        fontWeight: 600,
                        fontSize: "12px",
                        cursor: "pointer",
                    }}
                >
                    Todas ({notifications.length})
                </button>
                <button
                    type="button"
                    onClick={() => setFilter("unread")}
                    style={{
                        padding: "6px 14px",
                        borderRadius: "7px",
                        border: "none",
                        background: filter === "unread" ? "#0284c7" : "transparent",
                        color: filter === "unread" ? "#ffffff" : "#94a3b8",
                        fontWeight: 600,
                        fontSize: "12px",
                        cursor: "pointer",
                    }}
                >
                    No leídas ({unreadCount})
                </button>
                <button
                    type="button"
                    onClick={() => setFilter("read")}
                    style={{
                        padding: "6px 14px",
                        borderRadius: "7px",
                        border: "none",
                        background: filter === "read" ? "#0284c7" : "transparent",
                        color: filter === "read" ? "#ffffff" : "#94a3b8",
                        fontWeight: 600,
                        fontSize: "12px",
                        cursor: "pointer",
                    }}
                >
                    Leídas
                </button>
            </div>

            {/* ESTADOS DE CARGA Y ERROR */}
            {loading && (
                <div style={{ textAlign: "center", padding: "50px 20px" }}>
                    <div style={{ fontSize: "32px", color: "#38bdf8", marginBottom: "12px" }}>
                        <i className="fas fa-spinner fa-spin"></i>
                    </div>
                    <p style={{ color: "#94a3b8" }}>Cargando notificaciones...</p>
                </div>
            )}

            {error && (
                <div style={{ padding: "14px", borderRadius: "10px", background: "rgba(239, 68, 68, 0.12)", border: "1px solid rgba(239, 68, 68, 0.3)", color: "#f87171", marginBottom: "20px" }}>
                    <i className="fas fa-triangle-exclamation" style={{ marginRight: "8px" }}></i>
                    {error}
                </div>
            )}

            {!loading && !error && (
                <div className="notifications-wrapper">
                    {notifications.length === 0 ? (
                        <div style={{
                            textAlign: "center",
                            padding: "60px 20px",
                            background: "rgba(15, 23, 42, 0.5)",
                            borderRadius: "16px",
                            border: "1px dashed rgba(148, 163, 184, 0.2)",
                        }}>
                            <div style={{ fontSize: "36px", color: "#64748b", marginBottom: "12px" }}>
                                <i className="far fa-bell-slash"></i>
                            </div>
                            <h3 style={{ color: "#f8fafc", fontSize: "16px", fontWeight: 700 }}>
                                No tienes notificaciones en este filtro
                            </h3>
                            <p style={{ color: "#94a3b8", fontSize: "13px", marginTop: "4px" }}>
                                Cuando se publiquen nuevos simulacros, recordatorios o desbloquees logros, aparecerán aquí.
                            </p>
                        </div>
                    ) : (
                        <div className="notif-group">
                            {notifications.map((item) => {
                                const cfg = getTypeConfig(item.type);
                                return (
                                    <div
                                        key={item.id}
                                        className={`notif-card ${cfg.colorClass}`}
                                        onClick={() => !item.is_read && handleMarkAsRead(item.id)}
                                        style={{
                                            opacity: item.is_read ? 0.75 : 1,
                                            borderLeft: !item.is_read ? "3px solid #38bdf8" : undefined,
                                            display: "flex",
                                            justifyContent: "space-between",
                                            alignItems: "center",
                                        }}
                                    >
                                        {!item.is_read && <span className="status-dot"></span>}

                                        <div style={{ display: "flex", alignItems: "center", gap: "18px", flex: 1 }}>
                                            <div className="icon-box">
                                                <i className={`fas ${cfg.icon}`}></i>
                                            </div>

                                            <div className="notif-info" style={{ flex: 1 }}>
                                                <div style={{ display: "flex", alignItems: "center", gap: "8px", marginBottom: "3px" }}>
                                                    <h3 style={{ color: "#f8fafc", margin: 0 }}>{item.title}</h3>
                                                    <span style={{
                                                        fontSize: "10px",
                                                        padding: "2px 6px",
                                                        borderRadius: "6px",
                                                        background: "rgba(148, 163, 184, 0.15)",
                                                        color: "#94a3b8",
                                                        textTransform: "uppercase",
                                                    }}>
                                                        {cfg.label}
                                                    </span>
                                                </div>
                                                <p style={{ color: "#cbd5e1", margin: "2px 0 4px 0", fontSize: "13px", lineHeight: "1.5" }}>
                                                    {item.message}
                                                </p>
                                                <span className="time">{formatRelativeTime(item.created_at)}</span>
                                            </div>
                                        </div>

                                        {/* ACCIONES DE LA TARJETA */}
                                        <div style={{ display: "flex", alignItems: "center", gap: "10px", marginLeft: "14px" }}>
                                            {!item.is_read && (
                                                <button
                                                    type="button"
                                                    onClick={(e) => handleMarkAsRead(item.id, e)}
                                                    title="Marcar como leída"
                                                    style={{
                                                        background: "transparent",
                                                        border: "none",
                                                        color: "#38bdf8",
                                                        cursor: "pointer",
                                                        fontSize: "14px",
                                                        padding: "6px",
                                                    }}
                                                >
                                                    <i className="fas fa-check"></i>
                                                </button>
                                            )}

                                            <button
                                                type="button"
                                                onClick={(e) => handleDelete(item.id, e)}
                                                title="Eliminar notificación"
                                                style={{
                                                    background: "transparent",
                                                    border: "none",
                                                    color: "#64748b",
                                                    cursor: "pointer",
                                                    fontSize: "14px",
                                                    padding: "6px",
                                                }}
                                                onMouseEnter={(e) => (e.currentTarget.style.color = "#f87171")}
                                                onMouseLeave={(e) => (e.currentTarget.style.color = "#64748b")}
                                            >
                                                <i className="fas fa-trash-can"></i>
                                            </button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            )}

            {/* MASCOTA FLOTANTE ATHENA */}
            <div className="mascot-footer">
                <img src={Zorrito_10} alt="Zorrito Athena" className="floating-owl" />
            </div>
        </div>
    );
};

export default ProfileNotifications;
