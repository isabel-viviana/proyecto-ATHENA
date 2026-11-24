import { useEffect, useState } from "react";
import { useSearchParams } from "react-router-dom";
import "./users.css";
import api from "../../api/axios";
import {
    getAdminUsers,
    getAdminUser,
    createAdminUser,
    updateAdminUser,
    changeAdminUserRole,
    toggleAdminUserStatus,
} from "../../api/documents";

const AVATAR_GRADIENTS = [
    "linear-gradient(135deg, #10b981, #059669)",
    "linear-gradient(135deg, #3b82f6, #1d4ed8)",
    "linear-gradient(135deg, #a855f7, #7e22ce)",
    "linear-gradient(135deg, #f59e0b, #d97706)",
    "linear-gradient(135deg, #06b6d4, #0891b2)",
    "linear-gradient(135deg, #ec4899, #be185d)",
    "linear-gradient(135deg, #14b8a6, #0f766e)",
    "linear-gradient(135deg, #8b5cf6, #6d28d9)",
];

const ROLE_LABELS = {
    student: "Estudiante",
    admin: "Administrador",
    superadmin: "Super Admin",
    support: "Soporte",
};

const ROLE_COLORS = {
    student: { bg: "rgba(59, 130, 246, 0.14)", color: "#60a5fa", border: "rgba(59, 130, 246, 0.3)" },
    admin: { bg: "rgba(16, 185, 129, 0.14)", color: "#34d399", border: "rgba(16, 185, 129, 0.3)" },
    superadmin: { bg: "rgba(168, 85, 247, 0.16)", color: "#c084fc", border: "rgba(168, 85, 247, 0.35)" },
    support: { bg: "rgba(245, 158, 11, 0.15)", color: "#fbbf24", border: "rgba(245, 158, 11, 0.3)" },
};

const Users = () => {
    const [searchParams] = useSearchParams();
    const initialSearch = searchParams.get("search") || "";

    const [users, setUsers] = useState([]);
    const [stats, setStats] = useState({
        total: 0,
        active: 0,
        suspended: 0,
        avg_target_score: 0,
        by_role: { student: 0, admin: 0, superadmin: 0, support: 0 },
    });
    const [permissions, setPermissions] = useState({
        viewer_role: "admin",
        can_create_users: true,
        can_manage_roles: false,
    });
    const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0, per_page: 25 });

    const [searchTerm, setSearchTerm] = useState(initialSearch);
    const [roleFilter, setRoleFilter] = useState("Todos");
    const [statusFilter, setStatusFilter] = useState("Todos");
    const [gradeFilter, setGradeFilter] = useState("Todos");
    const [page, setPage] = useState(1);

    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [feedback, setFeedback] = useState(null);

    const [isFormOpen, setIsFormOpen] = useState(false);
    const [editingUser, setEditingUser] = useState(null);
    const [savingUser, setSavingUser] = useState(false);
    const [formError, setFormError] = useState("");
    const [formData, setFormData] = useState({
        full_name: "",
        email: "",
        password: "",
        phone: "",
        role: "student",
        grade: "11°",
        target_icfes_score: 380,
        status: "Activo",
    });

    const [detailModalOpen, setDetailModalOpen] = useState(false);
    const [detailUser, setDetailUser] = useState(null);
    const [loadingDetail, setLoadingDetail] = useState(false);

    useEffect(() => {
        const q = searchParams.get("search");
        if (q !== null && q !== searchTerm) {
            setSearchTerm(q);
            setPage(1);
        }
    }, [searchParams, searchTerm]);

    const loadUsers = async () => {
        setLoading(true);
        setError("");
        try {
            const res = await getAdminUsers({
                search: searchTerm.trim() || undefined,
                role: roleFilter !== "Todos" ? roleFilter : undefined,
                status: statusFilter !== "Todos" ? statusFilter : undefined,
                grade: gradeFilter !== "Todos" ? gradeFilter : undefined,
                page,
                per_page: 25,
            });
            if (res?.success) {
                setUsers(res.data || []);
                if (res.stats) setStats(res.stats);
                if (res.permissions) setPermissions(res.permissions);
                if (res.meta) setMeta(res.meta);
            }
        } catch (err) {
            setError(err?.response?.data?.message || "No se pudo consultar el directorio de usuarios.");
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        const timer = setTimeout(() => {
            loadUsers();
        }, 200);
        return () => clearTimeout(timer);
    }, [searchTerm, roleFilter, statusFilter, gradeFilter, page]);

    const showBanner = (type, text) => {
        setFeedback({ type, text });
        setTimeout(() => {
            setFeedback((prev) => (prev?.text === text ? null : prev));
        }, 5000);
    };

    const getInitials = (name = "") => {
        const parts = String(name).trim().split(/\s+/).filter(Boolean);
        if (parts.length >= 2) {
            return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
        }
        return String(name || "U").slice(0, 2).toUpperCase();
    };

    const formatDate = (iso) => {
        if (!iso) return "Sin registro";
        try {
            return new Date(iso).toLocaleString("es-CO", {
                year: "numeric",
                month: "short",
                day: "2-digit",
                hour: "2-digit",
                minute: "2-digit",
            });
        } catch {
            return String(iso);
        }
    };

    const handleOpenCreate = () => {
        setEditingUser(null);
        setFormError("");
        setFormData({
            full_name: "",
            email: "",
            password: "",
            phone: "",
            role: "student",
            grade: "11°",
            target_icfes_score: 380,
            status: "Activo",
        });
        setIsFormOpen(true);
    };

    const handleOpenEdit = (u) => {
        setEditingUser(u);
        setFormError("");
        setFormData({
            full_name: u.full_name || u.name || "",
            email: u.email || "",
            password: "",
            phone: u.phone || "",
            role: u.role || "student",
            grade: u.grade && u.grade !== "—" ? u.grade : "11°",
            target_icfes_score: u.target_icfes_score ?? u.targetScore ?? 380,
            status: u.status || "Activo",
        });
        setIsFormOpen(true);
    };

    const handleSubmitUser = async (e) => {
        e.preventDefault();
        setFormError("");

        if (!formData.full_name.trim() || !formData.email.trim()) {
            setFormError("El nombre completo y el correo electrónico son obligatorios.");
            return;
        }

        if (!editingUser && (!formData.password || formData.password.length < 6)) {
            setFormError("Debes asignar una contraseña de al menos 6 caracteres para el nuevo usuario.");
            return;
        }

        setSavingUser(true);
        try {
            const payload = {
                full_name: formData.full_name.trim(),
                email: formData.email.trim(),
                phone: formData.phone ? formData.phone.trim() : null,
                role: formData.role,
                grade: formData.role === "student" ? formData.grade : null,
                target_icfes_score:
                    formData.role === "student" ? Number(formData.target_icfes_score || 350) : null,
                status: formData.status,
            };
            if (formData.password && formData.password.trim() !== "") {
                payload.password = formData.password;
            }

            if (editingUser) {
                const res = await updateAdminUser(editingUser.id, payload);
                await api.put(`/users/${editingUser.id}`, payload).catch(() => {});
                showBanner("success", res?.message || "Usuario actualizado correctamente.");
            } else {
                const res = await createAdminUser(payload);
                showBanner("success", res?.message || "Usuario creado exitosamente.");
            }

            setIsFormOpen(false);
            await loadUsers();
            window.dispatchEvent(new Event("athena-admin-stats-refresh"));
        } catch (err) {
            const validationMsg = err?.response?.data?.errors
                ? Object.values(err.response.data.errors).flat().join(" ")
                : null;
            setFormError(validationMsg || err?.response?.data?.message || "Error al guardar el usuario.");
        } finally {
            setSavingUser(false);
        }
    };

    const handleViewDetail = async (u) => {
        setDetailUser(u);
        setDetailModalOpen(true);
        setLoadingDetail(true);
        try {
            const res = await getAdminUser(u.id);
            if (res?.success && res?.data) {
                setDetailUser(res.data);
            }
        } catch {
            // Mantiene datos básicos
        } finally {
            setLoadingDetail(false);
        }
    };

    const handleToggleStatus = async (u) => {
        const nextStatus = u.status === "Activo" ? "Suspendido" : "Activo";
        try {
            const res = await toggleAdminUserStatus(u.id, nextStatus);
            // Sincronizar bloqueo y revocación de tokens Sanctum en athena-backend
            await api.patch(`/users/${u.id}/status`, { status: nextStatus }).catch(() => {});
            showBanner("success", res?.message || `Estado cambiado a ${nextStatus}.`);
            await loadUsers();
            window.dispatchEvent(new Event("athena-admin-stats-refresh"));
        } catch (err) {
            showBanner("error", err?.response?.data?.message || "No se pudo cambiar el estado de la cuenta.");
        }
    };

    const handleQuickRoleChange = async (u, newRole) => {
        if (newRole === u.role) return;
        try {
            const res = await changeAdminUserRole(u.id, newRole);
            await api.patch(`/users/${u.id}/role`, { role: newRole }).catch(() => {});
            showBanner("success", res?.message || "Rol actualizado.");
            await loadUsers();
            window.dispatchEvent(new Event("athena-admin-stats-refresh"));
        } catch (err) {
            showBanner("error", err?.response?.data?.message || "No tienes permisos para cambiar este rol.");
        }
    };

    const adminAndSupportCount =
        (stats.by_role?.admin || 0) + (stats.by_role?.superadmin || 0) + (stats.by_role?.support || 0);

    return (
        <div className="usr-container">
            <header className="usr-header">
                <div className="usr-header-left">
                    <h1 className="usr-title">
                        <i className="fa-solid fa-users-gear"></i>
                        Gestión de Usuarios
                    </h1>
                    <p className="usr-subtitle">
                        Directorio oficial de cuentas en ATHENA Core — Estudiantes, Administradores y Soporte.
                    </p>
                </div>
                {permissions.can_create_users && (
                    <button className="usr-btn-new" onClick={handleOpenCreate} type="button">
                        <i className="fa-solid fa-user-plus"></i>
                        <span>+ Nuevo usuario</span>
                    </button>
                )}
            </header>

            {feedback && (
                <div
                    style={{
                        padding: "12px 16px",
                        borderRadius: "10px",
                        marginBottom: "18px",
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "space-between",
                        background:
                            feedback.type === "error"
                                ? "rgba(239, 68, 68, 0.14)"
                                : "rgba(16, 185, 129, 0.14)",
                        border:
                            feedback.type === "error"
                                ? "1px solid rgba(239, 68, 68, 0.35)"
                                : "1px solid rgba(16, 185, 129, 0.35)",
                        color: feedback.type === "error" ? "#fca5a5" : "#6ee7b7",
                        fontSize: "0.9rem",
                    }}
                >
                    <span>
                        <i
                            className={
                                feedback.type === "error"
                                    ? "fa-solid fa-circle-exclamation"
                                    : "fa-solid fa-circle-check"
                            }
                            style={{ marginRight: "8px" }}
                        ></i>
                        {feedback.text}
                    </span>
                    <button
                        type="button"
                        onClick={() => setFeedback(null)}
                        style={{ background: "none", border: "none", color: "inherit", cursor: "pointer" }}
                    >
                        <i className="fa-solid fa-xmark"></i>
                    </button>
                </div>
            )}

            <section className="usr-stats-grid">
                <div className="usr-stat-card">
                    <div className="usr-stat-info">
                        <span className="usr-stat-label">Total Usuarios</span>
                        <span className="usr-stat-val">{stats.total}</span>
                    </div>
                    <div className="usr-stat-icon-wrap usr-stat-icon--green">
                        <i className="fa-solid fa-users"></i>
                    </div>
                </div>

                <div className="usr-stat-card">
                    <div className="usr-stat-info">
                        <span className="usr-stat-label">Estudiantes</span>
                        <span className="usr-stat-val">{stats.by_role?.student || 0}</span>
                    </div>
                    <div className="usr-stat-icon-wrap usr-stat-icon--blue">
                        <i className="fa-solid fa-graduation-cap"></i>
                    </div>
                </div>

                <div className="usr-stat-card">
                    <div className="usr-stat-info">
                        <span className="usr-stat-label">Activos / Suspendidos</span>
                        <span className="usr-stat-val">
                            {stats.active}{" "}
                            <span style={{ fontSize: "0.9rem", color: "#ef4444" }}>
                                / {stats.suspended} susp.
                            </span>
                        </span>
                    </div>
                    <div className="usr-stat-icon-wrap usr-stat-icon--active">
                        <i className="fa-solid fa-user-check"></i>
                    </div>
                </div>

                <div className="usr-stat-card">
                    <div className="usr-stat-info">
                        <span className="usr-stat-label">Equipo Admin / Soporte</span>
                        <span className="usr-stat-val">
                            {adminAndSupportCount}{" "}
                            <span style={{ fontSize: "0.82rem", color: "#94a3b8" }}>
                                (Meta prom: {stats.avg_target_score || 0})
                            </span>
                        </span>
                    </div>
                    <div className="usr-stat-icon-wrap usr-stat-icon--red">
                        <i className="fa-solid fa-user-shield"></i>
                    </div>
                </div>
            </section>

            <section className="usr-filters-bar">
                <div className="usr-search-box">
                    <i className="fa-solid fa-magnifying-glass usr-search-icon"></i>
                    <input
                        type="text"
                        placeholder="Buscar por nombre o correo electrónico..."
                        className="usr-search-input"
                        value={searchTerm}
                        onChange={(e) => {
                            setSearchTerm(e.target.value);
                            setPage(1);
                        }}
                    />
                </div>

                <div className="usr-filter-group">
                    <div className="usr-select-wrap">
                        <label className="usr-select-label" htmlFor="filter-role">
                            Rol:
                        </label>
                        <select
                            id="filter-role"
                            className="usr-select"
                            value={roleFilter}
                            onChange={(e) => {
                                setRoleFilter(e.target.value);
                                setPage(1);
                            }}
                        >
                            <option value="Todos">Todos</option>
                            <option value="student">Estudiantes</option>
                            <option value="admin">Administradores</option>
                            <option value="superadmin">Super Admin</option>
                            <option value="support">Soporte</option>
                        </select>
                    </div>

                    <div className="usr-select-wrap">
                        <label className="usr-select-label" htmlFor="filter-status">
                            Estado:
                        </label>
                        <select
                            id="filter-status"
                            className="usr-select"
                            value={statusFilter}
                            onChange={(e) => {
                                setStatusFilter(e.target.value);
                                setPage(1);
                            }}
                        >
                            <option value="Todos">Todos</option>
                            <option value="Activo">Activos</option>
                            <option value="Suspendido">Suspendidos</option>
                        </select>
                    </div>

                    <div className="usr-select-wrap">
                        <label className="usr-select-label" htmlFor="filter-grade">
                            Grado:
                        </label>
                        <select
                            id="filter-grade"
                            className="usr-select"
                            value={gradeFilter}
                            onChange={(e) => {
                                setGradeFilter(e.target.value);
                                setPage(1);
                            }}
                        >
                            <option value="Todos">Todos</option>
                            <option value="10">10°</option>
                            <option value="11">11°</option>
                        </select>
                    </div>

                    <span className="usr-filter-count">
                        Mostrando <strong>{users.length}</strong> de {meta.total}
                    </span>
                </div>
            </section>

            <section className="usr-table-card">
                {error && (
                    <div style={{ padding: "16px", color: "#fca5a5", fontSize: "0.9rem" }}>
                        <i className="fa-solid fa-triangle-exclamation"></i> {error}
                    </div>
                )}

                <div className="usr-table-responsive">
                    <table className="usr-table">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Rol</th>
                                <th>Grado / Meta</th>
                                <th>Estado</th>
                                <th>Último Acceso</th>
                                <th style={{ textAlign: "center" }}>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {loading ? (
                                <tr>
                                    <td colSpan="6" style={{ textAlign: "center", padding: "36px", color: "#94a3b8" }}>
                                        <i className="fa-solid fa-circle-notch fa-spin" style={{ marginRight: "8px" }}></i>
                                        Cargando usuarios reales desde la base de datos...
                                    </td>
                                </tr>
                            ) : users.length > 0 ? (
                                users.map((u, idx) => {
                                    const roleStyle = ROLE_COLORS[u.role] || ROLE_COLORS.student;
                                    const canModifyThisUser =
                                        permissions.can_manage_roles || u.role === "student";

                                    return (
                                        <tr key={u.id}>
                                            <td>
                                                <div className="usr-student-cell">
                                                    <div
                                                        className="usr-avatar"
                                                        style={{
                                                            background:
                                                                AVATAR_GRADIENTS[idx % AVATAR_GRADIENTS.length],
                                                        }}
                                                    >
                                                        {getInitials(u.full_name || u.name)}
                                                    </div>
                                                    <div className="usr-name-wrap">
                                                        <span className="usr-name">{u.full_name || u.name}</span>
                                                        <span className="usr-email">{u.email}</span>
                                                    </div>
                                                </div>
                                            </td>

                                            <td>
                                                {permissions.can_manage_roles ? (
                                                    <select
                                                        value={u.role}
                                                        onChange={(e) => handleQuickRoleChange(u, e.target.value)}
                                                        style={{
                                                            background: roleStyle.bg,
                                                            color: roleStyle.color,
                                                            border: `1px solid ${roleStyle.border}`,
                                                            padding: "4px 8px",
                                                            borderRadius: "6px",
                                                            fontSize: "0.78rem",
                                                            fontWeight: 600,
                                                            cursor: "pointer",
                                                        }}
                                                    >
                                                        <option value="student" style={{ background: "#0f172a", color: "#f8fafc" }}>
                                                            Estudiante
                                                        </option>
                                                        <option value="admin" style={{ background: "#0f172a", color: "#f8fafc" }}>
                                                            Administrador
                                                        </option>
                                                        <option value="support" style={{ background: "#0f172a", color: "#f8fafc" }}>
                                                            Soporte
                                                        </option>
                                                        <option value="superadmin" style={{ background: "#0f172a", color: "#f8fafc" }}>
                                                            Super Admin
                                                        </option>
                                                    </select>
                                                ) : (
                                                    <span
                                                        style={{
                                                            background: roleStyle.bg,
                                                            color: roleStyle.color,
                                                            border: `1px solid ${roleStyle.border}`,
                                                            padding: "4px 10px",
                                                            borderRadius: "6px",
                                                            fontSize: "0.78rem",
                                                            fontWeight: 600,
                                                        }}
                                                    >
                                                        {ROLE_LABELS[u.role] || u.role}
                                                    </span>
                                                )}
                                            </td>

                                            <td>
                                                {u.role === "student" ? (
                                                    <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                                                        <span className="usr-grade-badge">
                                                            {u.grade || "Sin grado"}
                                                        </span>
                                                        {u.target_icfes_score ? (
                                                            <span className="usr-score">
                                                                {u.target_icfes_score}{" "}
                                                                <span className="usr-score-max">/ 500</span>
                                                            </span>
                                                        ) : null}
                                                    </div>
                                                ) : (
                                                    <span style={{ color: "#64748b", fontSize: "0.82rem" }}>
                                                        Personal Institucional
                                                    </span>
                                                )}
                                            </td>

                                            <td>
                                                <span
                                                    className={`usr-badge ${
                                                        u.status === "Activo"
                                                            ? "usr-badge--activo"
                                                            : "usr-badge--suspendido"
                                                    }`}
                                                >
                                                    <span className="usr-badge-dot"></span>
                                                    {u.status}
                                                </span>
                                            </td>

                                            <td style={{ fontSize: "0.8rem", color: "#94a3b8" }}>
                                                {formatDate(u.last_login_at || u.created_at)}
                                            </td>

                                            <td>
                                                <div className="usr-actions-cell" style={{ justifyContent: "center" }}>
                                                    <button
                                                        className="usr-action-icon-btn usr-action-icon-btn--view"
                                                        title="Consultar información y actividad"
                                                        type="button"
                                                        onClick={() => handleViewDetail(u)}
                                                    >
                                                        <i className="fa-solid fa-eye"></i>
                                                    </button>
                                                    {permissions.can_create_users && canModifyThisUser && (
                                                        <>
                                                            <button
                                                                className="usr-action-icon-btn usr-action-icon-btn--edit"
                                                                title="Editar usuario"
                                                                type="button"
                                                                onClick={() => handleOpenEdit(u)}
                                                            >
                                                                <i className="fa-solid fa-pen-to-square"></i>
                                                            </button>
                                                            <button
                                                                className="usr-action-icon-btn usr-action-icon-btn--suspend"
                                                                title={
                                                                    u.status === "Activo"
                                                                        ? "Desactivar / Suspender cuenta"
                                                                        : "Reactivar cuenta"
                                                                }
                                                                type="button"
                                                                onClick={() => handleToggleStatus(u)}
                                                            >
                                                                <i
                                                                    className={
                                                                        u.status === "Activo"
                                                                            ? "fa-solid fa-user-slash"
                                                                            : "fa-solid fa-user-check"
                                                                    }
                                                                ></i>
                                                            </button>
                                                        </>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })
                            ) : (
                                <tr>
                                    <td colSpan="6">
                                        <div className="usr-empty-state">
                                            <i className="fa-solid fa-user-slash"></i>
                                            <p>No se encontraron usuarios con los criterios seleccionados.</p>
                                        </div>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="usr-table-footer">
                    <span>
                        Página {meta.current_page} de {meta.last_page} ({meta.total} usuarios)
                    </span>
                    <div style={{ display: "flex", gap: "8px" }}>
                        <button
                            type="button"
                            disabled={meta.current_page <= 1}
                            onClick={() => setPage((p) => Math.max(1, p - 1))}
                            style={{
                                padding: "5px 12px",
                                borderRadius: "6px",
                                border: "1px solid rgba(148,163,184,0.2)",
                                background: "rgba(30,41,59,0.7)",
                                color: meta.current_page <= 1 ? "#475569" : "#e2e8f0",
                                cursor: meta.current_page <= 1 ? "not-allowed" : "pointer",
                            }}
                        >
                            Anterior
                        </button>
                        <button
                            type="button"
                            disabled={meta.current_page >= meta.last_page}
                            onClick={() => setPage((p) => p + 1)}
                            style={{
                                padding: "5px 12px",
                                borderRadius: "6px",
                                border: "1px solid rgba(148,163,184,0.2)",
                                background: "rgba(30,41,59,0.7)",
                                color: meta.current_page >= meta.last_page ? "#475569" : "#e2e8f0",
                                cursor: meta.current_page >= meta.last_page ? "not-allowed" : "pointer",
                            }}
                        >
                            Siguiente
                        </button>
                    </div>
                </div>
            </section>

            {isFormOpen && (
                <div
                    style={{
                        position: "fixed",
                        inset: 0,
                        background: "rgba(3, 7, 18, 0.78)",
                        backdropFilter: "blur(4px)",
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "center",
                        zIndex: 1000,
                        padding: "16px",
                    }}
                    onClick={() => setIsFormOpen(false)}
                >
                    <div
                        style={{
                            width: "100%",
                            maxWidth: "540px",
                            background: "#0f172a",
                            border: "1px solid rgba(148, 163, 184, 0.22)",
                            borderRadius: "16px",
                            padding: "24px",
                            color: "#f8fafc",
                            boxShadow: "0 20px 50px rgba(0,0,0,0.5)",
                        }}
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div
                            style={{
                                display: "flex",
                                justifyContent: "space-between",
                                alignItems: "center",
                                marginBottom: "18px",
                            }}
                        >
                            <h2 style={{ margin: 0, fontSize: "1.2rem", fontWeight: 700 }}>
                                <i
                                    className={
                                        editingUser
                                            ? "fa-solid fa-user-pen"
                                            : "fa-solid fa-user-plus"
                                    }
                                    style={{ marginRight: "10px", color: "#10b981" }}
                                ></i>
                                {editingUser ? "Editar Usuario" : "Crear Nuevo Usuario"}
                            </h2>
                            <button
                                type="button"
                                onClick={() => setIsFormOpen(false)}
                                style={{
                                    background: "transparent",
                                    border: "none",
                                    color: "#94a3b8",
                                    fontSize: "1.1rem",
                                    cursor: "pointer",
                                }}
                            >
                                <i className="fa-solid fa-xmark"></i>
                            </button>
                        </div>

                        {formError && (
                            <div
                                style={{
                                    padding: "10px 12px",
                                    borderRadius: "8px",
                                    background: "rgba(239, 68, 68, 0.15)",
                                    border: "1px solid rgba(239, 68, 68, 0.35)",
                                    color: "#fca5a5",
                                    fontSize: "0.85rem",
                                    marginBottom: "14px",
                                }}
                            >
                                {formError}
                            </div>
                        )}

                        <form onSubmit={handleSubmitUser} style={{ display: "flex", flexDirection: "column", gap: "14px" }}>
                            <div>
                                <label style={{ display: "block", fontSize: "0.82rem", color: "#cbd5e1", marginBottom: "5px" }}>
                                    Nombre Completo *
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={formData.full_name}
                                    onChange={(e) => setFormData({ ...formData, full_name: e.target.value })}
                                    placeholder="Ej. Laura Sofía Martínez"
                                    style={{
                                        width: "100%",
                                        padding: "10px 12px",
                                        borderRadius: "8px",
                                        border: "1px solid rgba(148,163,184,0.25)",
                                        background: "#1e293b",
                                        color: "#f8fafc",
                                    }}
                                />
                            </div>

                            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "12px" }}>
                                <div>
                                    <label style={{ display: "block", fontSize: "0.82rem", color: "#cbd5e1", marginBottom: "5px" }}>
                                        Correo Electrónico *
                                    </label>
                                    <input
                                        type="email"
                                        required
                                        value={formData.email}
                                        onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                                        placeholder="correo@institucion.edu.co"
                                        style={{
                                            width: "100%",
                                            padding: "10px 12px",
                                            borderRadius: "8px",
                                            border: "1px solid rgba(148,163,184,0.25)",
                                            background: "#1e293b",
                                            color: "#f8fafc",
                                        }}
                                    />
                                </div>

                                <div>
                                    <label style={{ display: "block", fontSize: "0.82rem", color: "#cbd5e1", marginBottom: "5px" }}>
                                        Teléfono (Opcional)
                                    </label>
                                    <input
                                        type="text"
                                        value={formData.phone}
                                        onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                                        placeholder="300 000 0000"
                                        style={{
                                            width: "100%",
                                            padding: "10px 12px",
                                            borderRadius: "8px",
                                            border: "1px solid rgba(148,163,184,0.25)",
                                            background: "#1e293b",
                                            color: "#f8fafc",
                                        }}
                                    />
                                </div>
                            </div>

                            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "12px" }}>
                                <div>
                                    <label style={{ display: "block", fontSize: "0.82rem", color: "#cbd5e1", marginBottom: "5px" }}>
                                        {editingUser ? "Nueva Contraseña (Opcional)" : "Contraseña *"}
                                    </label>
                                    <input
                                        type="password"
                                        required={!editingUser}
                                        value={formData.password}
                                        onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                                        placeholder={editingUser ? "Dejar vacío para conservar" : "Mínimo 6 caracteres"}
                                        style={{
                                            width: "100%",
                                            padding: "10px 12px",
                                            borderRadius: "8px",
                                            border: "1px solid rgba(148,163,184,0.25)",
                                            background: "#1e293b",
                                            color: "#f8fafc",
                                        }}
                                    />
                                </div>

                                <div>
                                    <label style={{ display: "block", fontSize: "0.82rem", color: "#cbd5e1", marginBottom: "5px" }}>
                                        Rol en el Sistema
                                    </label>
                                    <select
                                        value={formData.role}
                                        disabled={!permissions.can_manage_roles}
                                        onChange={(e) => setFormData({ ...formData, role: e.target.value })}
                                        style={{
                                            width: "100%",
                                            padding: "10px 12px",
                                            borderRadius: "8px",
                                            border: "1px solid rgba(148,163,184,0.25)",
                                            background: "#1e293b",
                                            color: "#f8fafc",
                                        }}
                                    >
                                        <option value="student">Estudiante</option>
                                        {permissions.can_manage_roles && (
                                            <>
                                                <option value="admin">Administrador</option>
                                                <option value="support">Soporte</option>
                                                <option value="superadmin">Super Administrador</option>
                                            </>
                                        )}
                                    </select>
                                </div>
                            </div>

                            {formData.role === "student" && (
                                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "12px" }}>
                                    <div>
                                        <label style={{ display: "block", fontSize: "0.82rem", color: "#cbd5e1", marginBottom: "5px" }}>
                                            Grado Escolar
                                        </label>
                                        <select
                                            value={formData.grade}
                                            onChange={(e) => setFormData({ ...formData, grade: e.target.value })}
                                            style={{
                                                width: "100%",
                                                padding: "10px 12px",
                                                borderRadius: "8px",
                                                border: "1px solid rgba(148,163,184,0.25)",
                                                background: "#1e293b",
                                                color: "#f8fafc",
                                            }}
                                        >
                                            <option value="9°">9°</option>
                                            <option value="10°">10°</option>
                                            <option value="11°">11°</option>
                                            <option value="Egresado">Egresado</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label style={{ display: "block", fontSize: "0.82rem", color: "#cbd5e1", marginBottom: "5px" }}>
                                            Puntaje ICFES Objetivo (0–500)
                                        </label>
                                        <input
                                            type="number"
                                            min="0"
                                            max="500"
                                            value={formData.target_icfes_score}
                                            onChange={(e) =>
                                                setFormData({ ...formData, target_icfes_score: e.target.value })
                                            }
                                            style={{
                                                width: "100%",
                                                padding: "10px 12px",
                                                borderRadius: "8px",
                                                border: "1px solid rgba(148,163,184,0.25)",
                                                background: "#1e293b",
                                                color: "#f8fafc",
                                            }}
                                        />
                                    </div>
                                </div>
                            )}

                            <div>
                                <label style={{ display: "block", fontSize: "0.82rem", color: "#cbd5e1", marginBottom: "5px" }}>
                                    Estado de la Cuenta
                                </label>
                                <select
                                    value={formData.status}
                                    onChange={(e) => setFormData({ ...formData, status: e.target.value })}
                                    style={{
                                        width: "100%",
                                        padding: "10px 12px",
                                        borderRadius: "8px",
                                        border: "1px solid rgba(148,163,184,0.25)",
                                        background: "#1e293b",
                                        color: "#f8fafc",
                                    }}
                                >
                                    <option value="Activo">Activo</option>
                                    <option value="Suspendido">Suspendido</option>
                                </select>
                            </div>

                            <div
                                style={{
                                    display: "flex",
                                    justifyContent: "flex-end",
                                    gap: "10px",
                                    marginTop: "10px",
                                }}
                            >
                                <button
                                    type="button"
                                    onClick={() => setIsFormOpen(false)}
                                    style={{
                                        padding: "10px 16px",
                                        borderRadius: "8px",
                                        border: "1px solid rgba(148,163,184,0.25)",
                                        background: "transparent",
                                        color: "#cbd5e1",
                                        cursor: "pointer",
                                    }}
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={savingUser}
                                    style={{
                                        padding: "10px 18px",
                                        borderRadius: "8px",
                                        border: "none",
                                        background: "linear-gradient(135deg, #10b981, #059669)",
                                        color: "#fff",
                                        fontWeight: 600,
                                        cursor: savingUser ? "not-allowed" : "pointer",
                                    }}
                                >
                                    {savingUser
                                        ? "Guardando..."
                                        : editingUser
                                        ? "Guardar Cambios"
                                        : "Crear Usuario"}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {detailModalOpen && detailUser && (
                <div
                    style={{
                        position: "fixed",
                        inset: 0,
                        background: "rgba(3, 7, 18, 0.78)",
                        backdropFilter: "blur(4px)",
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "center",
                        zIndex: 1000,
                        padding: "16px",
                    }}
                    onClick={() => setDetailModalOpen(false)}
                >
                    <div
                        style={{
                            width: "100%",
                            maxWidth: "520px",
                            background: "#0f172a",
                            border: "1px solid rgba(148, 163, 184, 0.22)",
                            borderRadius: "16px",
                            padding: "24px",
                            color: "#f8fafc",
                        }}
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div
                            style={{
                                display: "flex",
                                justifyContent: "space-between",
                                alignItems: "center",
                                marginBottom: "16px",
                            }}
                        >
                            <h3 style={{ margin: 0, fontSize: "1.15rem" }}>
                                <i className="fa-solid fa-id-card" style={{ marginRight: "8px", color: "#3b82f6" }}></i>
                                Ficha de Usuario #{detailUser.id}
                            </h3>
                            <button
                                type="button"
                                onClick={() => setDetailModalOpen(false)}
                                style={{
                                    background: "transparent",
                                    border: "none",
                                    color: "#94a3b8",
                                    fontSize: "1.1rem",
                                    cursor: "pointer",
                                }}
                            >
                                <i className="fa-solid fa-xmark"></i>
                            </button>
                        </div>

                        <div
                            style={{
                                background: "rgba(30, 41, 59, 0.6)",
                                borderRadius: "12px",
                                padding: "14px",
                                marginBottom: "14px",
                                display: "grid",
                                gridTemplateColumns: "1fr 1fr",
                                gap: "10px",
                                fontSize: "0.86rem",
                            }}
                        >
                            <div>
                                <div style={{ color: "#94a3b8", fontSize: "0.75rem" }}>Nombre Completo</div>
                                <div style={{ fontWeight: 600 }}>{detailUser.full_name || detailUser.name}</div>
                            </div>
                            <div>
                                <div style={{ color: "#94a3b8", fontSize: "0.75rem" }}>Correo Electrónico</div>
                                <div style={{ fontWeight: 600 }}>{detailUser.email}</div>
                            </div>
                            <div>
                                <div style={{ color: "#94a3b8", fontSize: "0.75rem" }}>Rol Asignado</div>
                                <div style={{ fontWeight: 600 }}>{ROLE_LABELS[detailUser.role] || detailUser.role}</div>
                            </div>
                            <div>
                                <div style={{ color: "#94a3b8", fontSize: "0.75rem" }}>Estado de Cuenta</div>
                                <div
                                    style={{
                                        fontWeight: 600,
                                        color: detailUser.status === "Activo" ? "#34d399" : "#f87171",
                                    }}
                                >
                                    {detailUser.status}
                                </div>
                            </div>
                            <div>
                                <div style={{ color: "#94a3b8", fontSize: "0.75rem" }}>Grado / Teléfono</div>
                                <div>
                                    {detailUser.grade || "—"} / {detailUser.phone || "Sin teléfono"}
                                </div>
                            </div>
                            <div>
                                <div style={{ color: "#94a3b8", fontSize: "0.75rem" }}>Fecha de Registro</div>
                                <div>{formatDate(detailUser.created_at)}</div>
                            </div>
                        </div>

                        {loadingDetail ? (
                            <div style={{ textAlign: "center", padding: "16px", color: "#94a3b8", fontSize: "0.85rem" }}>
                                <i className="fa-solid fa-circle-notch fa-spin"></i> Consultando actividad académica en Supabase...
                            </div>
                        ) : (
                            detailUser.academic_activity && (
                                <div
                                    style={{
                                        background: "rgba(16, 185, 129, 0.08)",
                                        border: "1px solid rgba(16, 185, 129, 0.25)",
                                        borderRadius: "12px",
                                        padding: "14px",
                                    }}
                                >
                                    <div
                                        style={{
                                            fontSize: "0.82rem",
                                            fontWeight: 700,
                                            color: "#34d399",
                                            marginBottom: "10px",
                                        }}
                                    >
                                        <i className="fa-solid fa-chart-simple" style={{ marginRight: "6px" }}></i>
                                        Actividad Académica en Question Bank (Supabase)
                                    </div>
                                    <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: "10px", textAlign: "center" }}>
                                        <div>
                                            <div style={{ fontSize: "1.15rem", fontWeight: 700 }}>
                                                {detailUser.academic_activity.completed_exams_count || 0} /{" "}
                                                {detailUser.academic_activity.exam_attempts_count || 0}
                                            </div>
                                            <div style={{ fontSize: "0.74rem", color: "#94a3b8" }}>Simulacros Fin.</div>
                                        </div>
                                        <div>
                                            <div style={{ fontSize: "1.15rem", fontWeight: 700 }}>
                                                {detailUser.academic_activity.answers_count || 0}
                                            </div>
                                            <div style={{ fontSize: "0.74rem", color: "#94a3b8" }}>Respuestas</div>
                                        </div>
                                        <div>
                                            <div style={{ fontSize: "1.15rem", fontWeight: 700, color: "#10b981" }}>
                                                {detailUser.academic_activity.accuracy || 0}%
                                            </div>
                                            <div style={{ fontSize: "0.74rem", color: "#94a3b8" }}>Precisión</div>
                                        </div>
                                    </div>
                                </div>
                            )
                        )}
                    </div>
                </div>
            )}
        </div>
    );
};

export default Users;
