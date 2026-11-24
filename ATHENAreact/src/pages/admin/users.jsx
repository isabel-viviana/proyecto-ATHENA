import { useEffect, useState } from "react";
import { useSearchParams } from "react-router-dom";
import "./users.css";
import api from "../../api/axios";
import useAuthStore, { normalizeUserRole } from "../../stores/useAuthStore";

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
    superadmin: "SuperAdmin",
    admin: "Administrador",
    student: "Estudiante",
};

const ROLE_ICONS = {
    superadmin: "fa-solid fa-crown",
    admin: "fa-solid fa-user-shield",
    student: "fa-solid fa-user-graduate",
};

const Users = () => {
    const [searchParams] = useSearchParams();
    const initialSearch = searchParams.get("search") || "";
    const initialStatus = searchParams.get("status") || "Todos";

    const currentUser = useAuthStore((s) => s.user);
    const currentUserId = Number(currentUser?.id || 0);

    const [users, setUsers] = useState([]);
    const [stats, setStats] = useState({
        total: 0,
        active: 0,
        suspended: 0,
        avg_target_score: 0,
        by_role: { superadmin: 0, admin: 0, student: 0 },
    });
    const [meta, setMeta] = useState({
        current_page: 1,
        last_page: 1,
        total: 0,
        per_page: 25,
    });

    const [searchTerm, setSearchTerm] = useState(initialSearch);
    const [roleFilter, setRoleFilter] = useState("Todos");
    const [statusFilter, setStatusFilter] = useState(initialStatus);
    const [page, setPage] = useState(1);

    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [feedback, setFeedback] = useState(null);

    // Modal de Crear / Editar Usuario
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

    // Modal de Detalle de Usuario
    const [detailModalOpen, setDetailModalOpen] = useState(false);
    const [detailUser, setDetailUser] = useState(null);

    // Modal de Confirmación (Bloquear / Activar / Cambiar Rol / Eliminar)
    const [confirmModal, setConfirmModal] = useState(null);
    const [processingAction, setProcessingAction] = useState(false);

    useEffect(() => {
        const q = searchParams.get("search");
        if (q !== null && q !== searchTerm) {
            setSearchTerm(q);
            setPage(1);
        }
    }, [searchParams]);

    const loadUsers = async () => {
        setLoading(true);
        setError("");
        try {
            const res = await api.get("/users", {
                params: {
                    search: searchTerm.trim() || undefined,
                    role: roleFilter !== "Todos" ? roleFilter : undefined,
                    status: statusFilter !== "Todos" ? statusFilter : undefined,
                    page,
                    per_page: 25,
                },
            });
            const payload = res.data;
            if (payload?.success) {
                setUsers(payload.data || []);
                if (payload.stats) setStats(payload.stats);
                if (payload.meta) setMeta(payload.meta);
            }
        } catch (err) {
            setError(
                err?.response?.data?.message ||
                    "No se pudo consultar el directorio de usuarios en PostgreSQL."
            );
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        const timer = setTimeout(() => {
            loadUsers();
        }, 180);
        return () => clearTimeout(timer);
    }, [searchTerm, roleFilter, statusFilter, page]);

    const showBanner = (type, text) => {
        setFeedback({ type, text });
        setTimeout(() => {
            setFeedback((prev) => (prev?.text === text ? null : prev));
        }, 5500);
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
        const normRole = normalizeUserRole(u.role);
        setEditingUser(u);
        setFormError("");
        setFormData({
            full_name: u.full_name || u.name || "",
            email: u.email || "",
            password: "",
            phone: u.phone || "",
            role: normRole === "superadmin" ? "superadmin" : normRole,
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
            setFormError("La contraseña debe tener al menos 6 caracteres.");
            return;
        }

        setSavingUser(true);
        try {
            const payload = {
                full_name: formData.full_name.trim(),
                email: formData.email.trim().toLowerCase(),
                phone: formData.phone ? formData.phone.trim() : null,
                role: formData.role,
                grade: formData.role === "student" ? formData.grade : null,
                target_icfes_score:
                    formData.role === "student"
                        ? Number(formData.target_icfes_score || 350)
                        : null,
                status: formData.status,
            };

            if (formData.password && formData.password.trim() !== "") {
                payload.password = formData.password;
            }

            if (editingUser) {
                const res = await api.put(`/users/${editingUser.id}`, payload);
                showBanner(
                    "success",
                    res.data?.message || "Usuario actualizado correctamente."
                );
            } else {
                const res = await api.post("/users", payload);
                showBanner(
                    "success",
                    res.data?.message || "Usuario creado exitosamente en PostgreSQL."
                );
            }

            setIsFormOpen(false);
            await loadUsers();
            window.dispatchEvent(new Event("athena-admin-stats-refresh"));
        } catch (err) {
            const validationMsg = err?.response?.data?.errors
                ? Object.values(err.response.data.errors).flat().join(" ")
                : null;
            setFormError(
                validationMsg ||
                    err?.response?.data?.message ||
                    "Error al guardar los datos del usuario."
            );
        } finally {
            setSavingUser(false);
        }
    };

    const requestRoleSwitch = (u) => {
        const currentRole = normalizeUserRole(u.role);
        if (currentRole === "superadmin") return;

        const targetRole = currentRole === "admin" ? "student" : "admin";
        const targetLabel = targetRole === "admin" ? "Administrador" : "Estudiante";

        setConfirmModal({
            type: "role",
            user: u,
            targetRole,
            title:
                targetRole === "admin"
                    ? "Promover a Administrador"
                    : "Cambiar rol a Estudiante",
            description:
                targetRole === "admin"
                    ? `¿Confirmas otorgar privilegios de Administrador Académico a ${u.full_name} (${u.email})? Podrá gestionar el Banco ICFES, simulacros y soporte.`
                    : `¿Confirmas cambiar el rol de ${u.full_name} (${u.email}) a Estudiante? Perderá el acceso al panel administrativo.`,
            confirmLabel: `Asignar rol ${targetLabel}`,
            isDanger: false,
        });
    };

    const requestStatusToggle = (u) => {
        const isCurrentlyActive = u.status === "Activo";
        const nextStatus = isCurrentlyActive ? "Suspendido" : "Activo";

        setConfirmModal({
            type: "status",
            user: u,
            nextStatus,
            title: isCurrentlyActive
                ? "Bloquear Cuenta de Usuario"
                : "Desbloquear Cuenta de Usuario",
            description: isCurrentlyActive
                ? `¿Confirmas bloquear la cuenta de ${u.full_name} (${u.email})? Se revocarán inmediatamente todos sus tokens de sesión activos en Sanctum.`
                : `¿Confirmas desbloquear y reactivar el acceso a la plataforma para ${u.full_name} (${u.email})?`,
            confirmLabel: isCurrentlyActive ? "Bloquear y Revocar Sesión" : "Activar Cuenta",
            isDanger: isCurrentlyActive,
        });
    };

    const requestDeleteUser = (u) => {
        setConfirmModal({
            type: "delete",
            user: u,
            title: "Eliminar Usuario Permanentemente",
            description: `¿Estás seguro de eliminar la cuenta de ${u.full_name} (${u.email}) de PostgreSQL? Esta acción revocará sus sesiones y no se puede deshacer.`,
            confirmLabel: "Eliminar Usuario",
            isDanger: true,
        });
    };

    const executeConfirmedAction = async () => {
        if (!confirmModal?.user) return;
        const u = confirmModal.user;
        setProcessingAction(true);

        try {
            if (confirmModal.type === "role") {
                const res = await api.patch(`/users/${u.id}/role`, {
                    role: confirmModal.targetRole,
                });
                showBanner(
                    "success",
                    res.data?.message || "Rol actualizado exitosamente."
                );
            } else if (confirmModal.type === "status") {
                const res = await api.patch(`/users/${u.id}/status`, {
                    status: confirmModal.nextStatus,
                });
                showBanner(
                    "success",
                    res.data?.message ||
                        `Estado actualizado a ${confirmModal.nextStatus}.`
                );
            } else if (confirmModal.type === "delete") {
                const res = await api.delete(`/users/${u.id}`);
                showBanner(
                    "success",
                    res.data?.message || "Usuario eliminado correctamente."
                );
            }

            setConfirmModal(null);
            await loadUsers();
            window.dispatchEvent(new Event("athena-admin-stats-refresh"));
        } catch (err) {
            showBanner(
                "error",
                err?.response?.data?.message ||
                    "No fue posible completar la operación solicitada."
            );
            setConfirmModal(null);
        } finally {
            setProcessingAction(false);
        }
    };

    return (
        <div className="usr-container">
            <header className="usr-header">
                <div className="usr-header-left">
                    <h1 className="usr-title">
                        <i className="fa-solid fa-users-gear"></i>
                        Gestionar Usuarios y Roles
                    </h1>
                    <p className="usr-subtitle">
                        Control exclusivo de SuperAdmin en PostgreSQL — Creación de cuentas, asignación de Administradores, bloqueo de sesiones y depuración.
                    </p>
                </div>
                <button className="usr-btn-new" onClick={handleOpenCreate} type="button">
                    <i className="fa-solid fa-user-plus"></i>
                    <span>Nuevo Usuario</span>
                </button>
            </header>

            {feedback && (
                <div
                    className={`usr-feedback-banner ${
                        feedback.type === "error"
                            ? "usr-feedback-banner--error"
                            : "usr-feedback-banner--success"
                    }`}
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
                        className="usr-feedback-close"
                        onClick={() => setFeedback(null)}
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
                        <span className="usr-stat-label">SuperAdmins</span>
                        <span className="usr-stat-val">{stats.by_role?.superadmin || 0}</span>
                    </div>
                    <div className="usr-stat-icon-wrap usr-stat-icon--red">
                        <i className="fa-solid fa-crown"></i>
                    </div>
                </div>

                <div className="usr-stat-card">
                    <div className="usr-stat-info">
                        <span className="usr-stat-label">Administradores</span>
                        <span className="usr-stat-val">{stats.by_role?.admin || 0}</span>
                    </div>
                    <div className="usr-stat-icon-wrap usr-stat-icon--active">
                        <i className="fa-solid fa-user-shield"></i>
                    </div>
                </div>

                <div className="usr-stat-card">
                    <div className="usr-stat-info">
                        <span className="usr-stat-label">Estudiantes / Estado</span>
                        <span className="usr-stat-val">
                            {stats.by_role?.student || 0}{" "}
                            <span style={{ fontSize: "0.84rem", color: "#94a3b8" }}>
                                ({stats.active} act. / {stats.suspended} bloq.)
                            </span>
                        </span>
                    </div>
                    <div className="usr-stat-icon-wrap usr-stat-icon--blue">
                        <i className="fa-solid fa-graduation-cap"></i>
                    </div>
                </div>
            </section>

            <section className="usr-filters-bar">
                <div className="usr-search-box">
                    <i className="fa-solid fa-magnifying-glass usr-search-icon"></i>
                    <input
                        type="text"
                        placeholder="Buscar usuario por nombre o correo electrónico..."
                        className="usr-search-input"
                        value={searchTerm}
                        onChange={(e) => {
                            setSearchTerm(e.target.value);
                            setPage(1);
                        }}
                    />
                </div>

                <div className="usr-filter-group">
                    <div className="usr-role-tabs" role="tablist" aria-label="Filtrar por rol">
                        {[
                            { key: "Todos", label: "Todos" },
                            { key: "superadmin", label: "SuperAdmin" },
                            { key: "admin", label: "Admin" },
                            { key: "student", label: "Estudiante" },
                        ].map((tab) => (
                            <button
                                key={tab.key}
                                type="button"
                                className={`usr-role-tab ${
                                    roleFilter === tab.key ? "usr-role-tab--active" : ""
                                }`}
                                onClick={() => {
                                    setRoleFilter(tab.key);
                                    setPage(1);
                                }}
                            >
                                {tab.label}
                            </button>
                        ))}
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
                            <option value="Suspendido">Bloqueados</option>
                        </select>
                    </div>

                    <span className="usr-filter-count">
                        Mostrando <strong>{users.length}</strong> de {meta.total}
                    </span>
                </div>
            </section>

            <section className="usr-table-card">
                {error && (
                    <div className="usr-feedback-banner usr-feedback-banner--error" style={{ margin: "16px" }}>
                        <span>
                            <i className="fa-solid fa-triangle-exclamation" style={{ marginRight: "8px" }}></i>
                            {error}
                        </span>
                    </div>
                )}

                <div className="usr-table-responsive">
                    <table className="usr-table">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Rol Actual</th>
                                <th>Cambio Rápido de Rol</th>
                                <th>Grado / Meta ICFES</th>
                                <th>Estado</th>
                                <th>Último Acceso</th>
                                <th style={{ textAlign: "center" }}>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {loading ? (
                                <tr>
                                    <td colSpan="7" style={{ textAlign: "center", padding: "36px", color: "#94a3b8" }}>
                                        <i className="fa-solid fa-circle-notch fa-spin" style={{ marginRight: "8px" }}></i>
                                        Consultando directorio de usuarios en PostgreSQL...
                                    </td>
                                </tr>
                            ) : users.length > 0 ? (
                                users.map((u, idx) => {
                                    const normRole = normalizeUserRole(u.role);
                                    const isSelf = Number(u.id) === currentUserId;
                                    const isProtectedSuper =
                                        normRole === "superadmin" || Boolean(u.is_master_admin);

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
                                                        <span className="usr-name">
                                                            {u.full_name || u.name}
                                                            {isSelf && (
                                                                <span className="usr-self-tag">Sesión Actual</span>
                                                            )}
                                                        </span>
                                                        <span className="usr-email">{u.email}</span>
                                                    </div>
                                                </div>
                                            </td>

                                            <td>
                                                <span className={`usr-role-pill usr-role-pill--${normRole}`}>
                                                    <i className={ROLE_ICONS[normRole] || "fa-solid fa-user"}></i>
                                                    <span>{ROLE_LABELS[normRole] || normRole}</span>
                                                </span>
                                            </td>

                                            <td>
                                                {isProtectedSuper ? (
                                                    <span style={{ fontSize: "0.78rem", color: "#64748b" }}>
                                                        Rol protegido
                                                    </span>
                                                ) : normRole === "student" ? (
                                                    <button
                                                        type="button"
                                                        className="usr-role-switch-btn usr-role-switch-btn--promote"
                                                        onClick={() => requestRoleSwitch(u)}
                                                        title="Promover estudiante a Administrador"
                                                    >
                                                        <i className="fa-solid fa-user-shield"></i>
                                                        <span>Asignar Admin</span>
                                                    </button>
                                                ) : (
                                                    <button
                                                        type="button"
                                                        className="usr-role-switch-btn usr-role-switch-btn--demote"
                                                        onClick={() => requestRoleSwitch(u)}
                                                        title="Cambiar Administrador a Estudiante"
                                                    >
                                                        <i className="fa-solid fa-user-graduate"></i>
                                                        <span>Pasar a Estudiante</span>
                                                    </button>
                                                )}
                                            </td>

                                            <td>
                                                {normRole === "student" ? (
                                                    <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                                                        <span className="usr-grade-badge">
                                                            {u.grade || "11°"}
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
                                                        Gestión Institucional
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
                                                    {u.status === "Activo" ? "Activo" : "Bloqueado"}
                                                </span>
                                            </td>

                                            <td style={{ fontSize: "0.8rem", color: "#94a3b8" }}>
                                                {formatDate(u.last_login_at || u.created_at)}
                                            </td>

                                            <td>
                                                <div className="usr-actions-cell" style={{ justifyContent: "center" }}>
                                                    <button
                                                        className="usr-action-icon-btn usr-action-icon-btn--view"
                                                        title="Ver ficha completa"
                                                        type="button"
                                                        onClick={() => {
                                                            setDetailUser(u);
                                                            setDetailModalOpen(true);
                                                        }}
                                                    >
                                                        <i className="fa-solid fa-eye"></i>
                                                    </button>

                                                    <button
                                                        className="usr-action-icon-btn usr-action-icon-btn--edit"
                                                        title="Editar datos y rol"
                                                        type="button"
                                                        onClick={() => handleOpenEdit(u)}
                                                    >
                                                        <i className="fa-solid fa-pen-to-square"></i>
                                                    </button>

                                                    <button
                                                        className="usr-action-icon-btn usr-action-icon-btn--suspend"
                                                        title={
                                                            isSelf || isProtectedSuper
                                                                ? "No se puede bloquear una cuenta SuperAdmin"
                                                                : u.status === "Activo"
                                                                ? "Bloquear cuenta y revocar tokens"
                                                                : "Desbloquear cuenta"
                                                        }
                                                        type="button"
                                                        disabled={isSelf || isProtectedSuper}
                                                        onClick={() => requestStatusToggle(u)}
                                                    >
                                                        <i
                                                            className={
                                                                u.status === "Activo"
                                                                    ? "fa-solid fa-lock"
                                                                    : "fa-solid fa-lock-open"
                                                            }
                                                        ></i>
                                                    </button>

                                                    <button
                                                        className="usr-action-icon-btn usr-action-icon-btn--delete"
                                                        title={
                                                            isSelf || isProtectedSuper
                                                                ? "No se puede eliminar una cuenta SuperAdmin"
                                                                : "Eliminar usuario"
                                                        }
                                                        type="button"
                                                        disabled={isSelf || isProtectedSuper}
                                                        onClick={() => requestDeleteUser(u)}
                                                    >
                                                        <i className="fa-solid fa-trash-can"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })
                            ) : (
                                <tr>
                                    <td colSpan="7">
                                        <div className="usr-empty-state">
                                            <i className="fa-solid fa-user-slash"></i>
                                            <p>No se encontraron usuarios con los filtros seleccionados.</p>
                                        </div>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="usr-table-footer">
                    <span>
                        Página {meta.current_page} de {meta.last_page} ({meta.total} registros)
                    </span>
                    <div style={{ display: "flex", gap: "8px" }}>
                        <button
                            type="button"
                            className="usr-btn-cancel"
                            disabled={meta.current_page <= 1}
                            onClick={() => setPage((p) => Math.max(1, p - 1))}
                            style={{ padding: "6px 12px", fontSize: "0.78rem" }}
                        >
                            Anterior
                        </button>
                        <button
                            type="button"
                            className="usr-btn-cancel"
                            disabled={meta.current_page >= meta.last_page}
                            onClick={() => setPage((p) => p + 1)}
                            style={{ padding: "6px 12px", fontSize: "0.78rem" }}
                        >
                            Siguiente
                        </button>
                    </div>
                </div>
            </section>

            {/* MODAL CREAR / EDITAR USUARIO */}
            {isFormOpen && (
                <div className="usr-modal-overlay" onClick={() => setIsFormOpen(false)}>
                    <div className="usr-modal-card" onClick={(e) => e.stopPropagation()}>
                        <div className="usr-modal-header">
                            <h2 className="usr-modal-title">
                                <i
                                    className={
                                        editingUser ? "fa-solid fa-user-pen" : "fa-solid fa-user-plus"
                                    }
                                    style={{ color: "#22c55e" }}
                                ></i>
                                <span>{editingUser ? "Editar Usuario y Rol" : "Crear Nuevo Usuario"}</span>
                            </h2>
                            <button
                                type="button"
                                className="usr-modal-close"
                                onClick={() => setIsFormOpen(false)}
                            >
                                <i className="fa-solid fa-xmark"></i>
                            </button>
                        </div>

                        {formError && (
                            <div
                                className="usr-feedback-banner usr-feedback-banner--error"
                                style={{ marginBottom: "14px" }}
                            >
                                <span>{formError}</span>
                            </div>
                        )}

                        <form onSubmit={handleSubmitUser} className="usr-form">
                            <div>
                                <label className="usr-form-label">Nombre Completo *</label>
                                <input
                                    type="text"
                                    required
                                    className="usr-form-input"
                                    value={formData.full_name}
                                    onChange={(e) =>
                                        setFormData({ ...formData, full_name: e.target.value })
                                    }
                                    placeholder="Ej. Laura Sofía Martínez"
                                />
                            </div>

                            <div className="usr-form-row">
                                <div>
                                    <label className="usr-form-label">Correo Electrónico *</label>
                                    <input
                                        type="email"
                                        required
                                        className="usr-form-input"
                                        value={formData.email}
                                        onChange={(e) =>
                                            setFormData({ ...formData, email: e.target.value })
                                        }
                                        placeholder="usuario@athena.edu.co"
                                    />
                                </div>

                                <div>
                                    <label className="usr-form-label">Teléfono (Opcional)</label>
                                    <input
                                        type="text"
                                        className="usr-form-input"
                                        value={formData.phone}
                                        onChange={(e) =>
                                            setFormData({ ...formData, phone: e.target.value })
                                        }
                                        placeholder="300 000 0000"
                                    />
                                </div>
                            </div>

                            <div className="usr-form-row">
                                <div>
                                    <label className="usr-form-label">
                                        {editingUser ? "Nueva Contraseña (Opcional)" : "Contraseña *"}
                                    </label>
                                    <input
                                        type="password"
                                        required={!editingUser}
                                        className="usr-form-input"
                                        value={formData.password}
                                        onChange={(e) =>
                                            setFormData({ ...formData, password: e.target.value })
                                        }
                                        placeholder={
                                            editingUser
                                                ? "Dejar vacío para mantener"
                                                : "Mínimo 6 caracteres"
                                        }
                                    />
                                </div>

                                <div>
                                    <label className="usr-form-label">Rol en el Sistema *</label>
                                    <select
                                        className="usr-form-select"
                                        value={formData.role}
                                        disabled={
                                            editingUser &&
                                            (Number(editingUser.id) === currentUserId ||
                                                normalizeUserRole(editingUser.role) === "superadmin")
                                        }
                                        onChange={(e) =>
                                            setFormData({ ...formData, role: e.target.value })
                                        }
                                    >
                                        <option value="student">Estudiante</option>
                                        <option value="admin">Administrador</option>
                                        {editingUser &&
                                            normalizeUserRole(editingUser.role) === "superadmin" && (
                                                <option value="superadmin">SuperAdmin</option>
                                            )}
                                    </select>
                                </div>
                            </div>

                            {formData.role === "student" && (
                                <div className="usr-form-row">
                                    <div>
                                        <label className="usr-form-label">Grado Escolar</label>
                                        <select
                                            className="usr-form-select"
                                            value={formData.grade}
                                            onChange={(e) =>
                                                setFormData({ ...formData, grade: e.target.value })
                                            }
                                        >
                                            <option value="9°">9°</option>
                                            <option value="10°">10°</option>
                                            <option value="11°">11°</option>
                                            <option value="Egresado">Egresado</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label className="usr-form-label">
                                            Puntaje ICFES Objetivo (0–500)
                                        </label>
                                        <input
                                            type="number"
                                            min="0"
                                            max="500"
                                            className="usr-form-input"
                                            value={formData.target_icfes_score}
                                            onChange={(e) =>
                                                setFormData({
                                                    ...formData,
                                                    target_icfes_score: e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                </div>
                            )}

                            <div>
                                <label className="usr-form-label">Estado de la Cuenta</label>
                                <select
                                    className="usr-form-select"
                                    value={formData.status}
                                    disabled={
                                        editingUser &&
                                        (Number(editingUser.id) === currentUserId ||
                                            normalizeUserRole(editingUser.role) === "superadmin")
                                    }
                                    onChange={(e) =>
                                        setFormData({ ...formData, status: e.target.value })
                                    }
                                >
                                    <option value="Activo">Activo</option>
                                    <option value="Suspendido">Bloqueado / Suspendido</option>
                                </select>
                            </div>

                            <div className="usr-modal-actions">
                                <button
                                    type="button"
                                    className="usr-btn-cancel"
                                    onClick={() => setIsFormOpen(false)}
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    className="usr-btn-submit"
                                    disabled={savingUser}
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

            {/* MODAL CONFIRMACIÓN DE ACCIÓN (ROL / BLOQUEO / ELIMINACIÓN) */}
            {confirmModal && (
                <div className="usr-modal-overlay" onClick={() => setConfirmModal(null)}>
                    <div
                        className="usr-modal-card usr-modal-card--confirm"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="usr-modal-header">
                            <h3 className="usr-modal-title">
                                <i
                                    className={
                                        confirmModal.isDanger
                                            ? "fa-solid fa-triangle-exclamation"
                                            : "fa-solid fa-user-shield"
                                    }
                                    style={{
                                        color: confirmModal.isDanger ? "#ef4444" : "#22c55e",
                                    }}
                                ></i>
                                <span>{confirmModal.title}</span>
                            </h3>
                            <button
                                type="button"
                                className="usr-modal-close"
                                onClick={() => setConfirmModal(null)}
                            >
                                <i className="fa-solid fa-xmark"></i>
                            </button>
                        </div>

                        <p style={{ color: "#cbd5e1", fontSize: "0.9rem", lineHeight: 1.55, margin: 0 }}>
                            {confirmModal.description}
                        </p>

                        <div className="usr-modal-actions">
                            <button
                                type="button"
                                className="usr-btn-cancel"
                                disabled={processingAction}
                                onClick={() => setConfirmModal(null)}
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                className={
                                    confirmModal.isDanger ? "usr-btn-danger" : "usr-btn-submit"
                                }
                                disabled={processingAction}
                                onClick={executeConfirmedAction}
                            >
                                {processingAction ? "Procesando..." : confirmModal.confirmLabel}
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* MODAL FICHA DETALLE DE USUARIO */}
            {detailModalOpen && detailUser && (
                <div className="usr-modal-overlay" onClick={() => setDetailModalOpen(false)}>
                    <div className="usr-modal-card" onClick={(e) => e.stopPropagation()}>
                        <div className="usr-modal-header">
                            <h3 className="usr-modal-title">
                                <i className="fa-solid fa-id-card" style={{ color: "#38bdf8" }}></i>
                                <span>Ficha de Usuario #{detailUser.id}</span>
                            </h3>
                            <button
                                type="button"
                                className="usr-modal-close"
                                onClick={() => setDetailModalOpen(false)}
                            >
                                <i className="fa-solid fa-xmark"></i>
                            </button>
                        </div>

                        <div
                            style={{
                                background: "rgba(30, 41, 59, 0.6)",
                                borderRadius: "12px",
                                padding: "16px",
                                display: "grid",
                                gridTemplateColumns: "1fr 1fr",
                                gap: "12px",
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
                                <div style={{ color: "#94a3b8", fontSize: "0.75rem" }}>Rol Activo</div>
                                <div style={{ fontWeight: 600 }}>
                                    {ROLE_LABELS[normalizeUserRole(detailUser.role)] || detailUser.role}
                                </div>
                            </div>
                            <div>
                                <div style={{ color: "#94a3b8", fontSize: "0.75rem" }}>Estado de Cuenta</div>
                                <div
                                    style={{
                                        fontWeight: 600,
                                        color: detailUser.status === "Activo" ? "#34d399" : "#f87171",
                                    }}
                                >
                                    {detailUser.status === "Activo" ? "Activo" : "Bloqueado"}
                                </div>
                            </div>
                            <div>
                                <div style={{ color: "#94a3b8", fontSize: "0.75rem" }}>Grado / Teléfono</div>
                                <div>
                                    {detailUser.grade || "N/A"} / {detailUser.phone || "Sin teléfono"}
                                </div>
                            </div>
                            <div>
                                <div style={{ color: "#94a3b8", fontSize: "0.75rem" }}>Fecha de Registro</div>
                                <div>{formatDate(detailUser.created_at)}</div>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default Users;
