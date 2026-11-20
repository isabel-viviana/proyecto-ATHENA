import { useState, useMemo, useEffect, useCallback } from "react";
import "./users.css";
import api from "../../api/axios";

const avatarGradients = [
    "linear-gradient(135deg, #10b981, #059669)",
    "linear-gradient(135deg, #3b82f6, #1d4ed8)",
    "linear-gradient(135deg, #a855f7, #7e22ce)",
    "linear-gradient(135deg, #f59e0b, #d97706)",
    "linear-gradient(135deg, #06b6d4, #0891b2)",
    "linear-gradient(135deg, #ec4899, #be185d)",
    "linear-gradient(135deg, #14b8a6, #0f766e)",
    "linear-gradient(135deg, #8b5cf6, #6d28d9)",
];

const Users = () => {
    const [students, setStudents] = useState([]);
    const [loading, setLoading] = useState(true);
    const [togglingId, setTogglingId] = useState(null);
    const [feedbackMsg, setFeedbackMsg] = useState("");
    const [searchTerm, setSearchTerm] = useState("");
    const [statusFilter, setStatusFilter] = useState("Todos");
    const [gradeFilter, setGradeFilter] = useState("Todos");

    const fetchUsers = useCallback(async () => {
        setLoading(true);
        try {
            const res = await api.get("/users");
            const list = Array.isArray(res?.data?.data) ? res.data.data : [];
            const mapped = list.map((u, idx) => ({
                id: u.id,
                name: u.full_name || u.name || "Usuario",
                email: u.email || "",
                role: u.role || "student",
                grade: u.grade || "11° A",
                targetScore: Number(u.target_icfes_score || u.targetScore || 380),
                status: u.status === "Suspendido" || u.raw_status === "blocked" ? "Suspendido" : "Activo",
                lastAccess: u.last_login_at
                    ? new Date(u.last_login_at).toLocaleString("es-CO", {
                          day: "2-digit",
                          month: "short",
                          hour: "2-digit",
                          minute: "2-digit",
                      })
                    : "Sin registro reciente",
                avatarBg: avatarGradients[idx % avatarGradients.length],
            }));
            setStudents(mapped);
        } catch (err) {
            console.error("Error cargando usuarios:", err);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchUsers();
    }, [fetchUsers]);

    const filteredStudents = useMemo(() => {
        return students.filter((student) => {
            const matchesSearch =
                student.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                student.email.toLowerCase().includes(searchTerm.toLowerCase());

            const matchesStatus =
                statusFilter === "Todos" || student.status === statusFilter;

            const matchesGrade =
                gradeFilter === "Todos" || student.grade.startsWith(gradeFilter);

            return matchesSearch && matchesStatus && matchesGrade;
        });
    }, [students, searchTerm, statusFilter, gradeFilter]);

    const totalCount = students.length;
    const activeCount = students.filter((s) => s.status === "Activo").length;
    const suspendedCount = students.filter((s) => s.status === "Suspendido").length;
    const averageTargetScore =
        totalCount > 0
            ? Math.round(
                  students.reduce((acc, s) => acc + s.targetScore, 0) / totalCount
              )
            : 0;

    const handleView = (student) => {
        setFeedbackMsg(`Usuario: ${student.name} (${student.email}) — Estado: ${student.status}`);
    };

    const handleEdit = (student) => {
        setFeedbackMsg(`Edición seleccionada para: ${student.name}`);
    };

    // Bloquear o reactivar usuario en PostgreSQL y revocar tokens de Sanctum
    const handleSuspend = async (student) => {
        if (togglingId) return;
        setTogglingId(student.id);
        setFeedbackMsg("");

        try {
            const nextStatus = student.status === "Activo" ? "blocked" : "active";
            const res = await api.patch(`/users/${student.id}/status`, {
                status: nextStatus,
            });

            const updatedLabel =
                res?.data?.data?.status ||
                (nextStatus === "blocked" ? "Suspendido" : "Activo");

            setStudents((prev) =>
                prev.map((s) =>
                    s.id === student.id ? { ...s, status: updatedLabel } : s
                )
            );
            setFeedbackMsg(
                res?.data?.message ||
                    `Estado de ${student.name} actualizado a ${updatedLabel}.`
            );
        } catch (err) {
            setFeedbackMsg(
                err?.response?.data?.message ||
                    "No fue posible cambiar el estado del usuario."
            );
        } finally {
            setTogglingId(null);
        }
    };

    const handleCreateNew = () => {
        fetchUsers();
    };

    const getInitials = (name) => {
        const parts = name.trim().split(" ");
        if (parts.length >= 2) {
            return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
        }
        return name.slice(0, 2).toUpperCase();
    };

    return (
        <div className="usr-container">
            {/* 1. HEADER */}
            <header className="usr-header">
                <div className="usr-header-left">
                    <h1 className="usr-title">
                        <i className="fa-solid fa-users-gear"></i>
                        Gestión de Estudiantes
                    </h1>
                    <p className="usr-subtitle">
                        Administra los usuarios registrados en ATHENA.
                    </p>
                </div>
                <button
                    className="usr-btn-new"
                    onClick={handleCreateNew}
                    type="button"
                >
                    <i className="fa-solid fa-rotate"></i>
                    <span>Actualizar lista</span>
                </button>
            </header>

            {feedbackMsg && (
                <div
                    style={{
                        padding: "12px 16px",
                        borderRadius: "10px",
                        marginBottom: "18px",
                        background: "rgba(99, 102, 241, 0.14)",
                        border: "1px solid rgba(99, 102, 241, 0.35)",
                        color: "#e2e8f0",
                        fontSize: "13px",
                        display: "flex",
                        justifyContent: "space-between",
                        alignItems: "center",
                    }}
                >
                    <span>{feedbackMsg}</span>
                    <button
                        type="button"
                        onClick={() => setFeedbackMsg("")}
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
            )}

            {/* 2. PANEL RESUMEN (TARJETAS STATS) */}
            <section className="usr-stats-grid">
                <div className="usr-stat-card">
                    <div className="usr-stat-info">
                        <span className="usr-stat-label">Total Usuarios</span>
                        <span className="usr-stat-val">{totalCount}</span>
                    </div>
                    <div className="usr-stat-icon-wrap usr-stat-icon--green">
                        <i className="fa-solid fa-graduation-cap"></i>
                    </div>
                </div>

                <div className="usr-stat-card">
                    <div className="usr-stat-info">
                        <span className="usr-stat-label">Activos</span>
                        <span className="usr-stat-val">{activeCount}</span>
                    </div>
                    <div className="usr-stat-icon-wrap usr-stat-icon--active">
                        <i className="fa-solid fa-user-check"></i>
                    </div>
                </div>

                <div className="usr-stat-card">
                    <div className="usr-stat-info">
                        <span className="usr-stat-label">Suspendidos / Bloqueados</span>
                        <span className="usr-stat-val">{suspendedCount}</span>
                    </div>
                    <div className="usr-stat-icon-wrap usr-stat-icon--red">
                        <i className="fa-solid fa-user-xmark"></i>
                    </div>
                </div>

                <div className="usr-stat-card">
                    <div className="usr-stat-info">
                        <span className="usr-stat-label">Promedio Objetivo</span>
                        <span className="usr-stat-val">
                            {averageTargetScore}{" "}
                            <span style={{ fontSize: "0.9rem", color: "#94a3b8" }}>
                                / 500
                            </span>
                        </span>
                    </div>
                    <div className="usr-stat-icon-wrap usr-stat-icon--blue">
                        <i className="fa-solid fa-bullseye"></i>
                    </div>
                </div>
            </section>

            {/* 3. BARRA SUPERIOR (BUSCADOR Y FILTROS) */}
            <section className="usr-filters-bar">
                <div className="usr-search-box">
                    <i className="fa-solid fa-magnifying-glass usr-search-icon"></i>
                    <input
                        type="text"
                        placeholder="Buscar por nombre o correo electrónico..."
                        className="usr-search-input"
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                    />
                </div>

                <div className="usr-filter-group">
                    <div className="usr-select-wrap">
                        <label className="usr-select-label" htmlFor="filter-status">
                            Estado:
                        </label>
                        <select
                            id="filter-status"
                            className="usr-select"
                            value={statusFilter}
                            onChange={(e) => setStatusFilter(e.target.value)}
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
                            onChange={(e) => setGradeFilter(e.target.value)}
                        >
                            <option value="Todos">Todos</option>
                            <option value="10°">10°</option>
                            <option value="11°">11°</option>
                        </select>
                    </div>

                    <span className="usr-filter-count">
                        Mostrando <strong>{filteredStudents.length}</strong> de {totalCount}
                    </span>
                </div>
            </section>

            {/* 4. TABLA MODERNA */}
            <section className="usr-table-card">
                <div className="usr-table-responsive">
                    <table className="usr-table">
                        <thead>
                            <tr>
                                <th>Estudiante</th>
                                <th>Grado</th>
                                <th>Puntaje Objetivo</th>
                                <th>Estado</th>
                                <th>Último Acceso</th>
                                <th style={{ textAlign: "center" }}>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {loading ? (
                                <tr>
                                    <td colSpan="6">
                                        <div className="usr-empty-state">
                                            <i className="fa-solid fa-circle-notch fa-spin"></i>
                                            <p>Cargando usuarios desde PostgreSQL...</p>
                                        </div>
                                    </td>
                                </tr>
                            ) : filteredStudents.length > 0 ? (
                                filteredStudents.map((student) => (
                                    <tr key={student.id}>
                                        <td>
                                            <div className="usr-student-cell">
                                                <div
                                                    className="usr-avatar"
                                                    style={{ background: student.avatarBg }}
                                                >
                                                    {getInitials(student.name)}
                                                </div>
                                                <div className="usr-name-wrap">
                                                    <span className="usr-name">{student.name}</span>
                                                    <span className="usr-email">{student.email}</span>
                                                </div>
                                            </div>
                                        </td>

                                        <td>
                                            <span className="usr-grade-badge">
                                                {student.grade}
                                            </span>
                                        </td>

                                        <td>
                                            <span className="usr-score">
                                                {student.targetScore}{" "}
                                                <span className="usr-score-max">/ 500</span>
                                            </span>
                                        </td>

                                        <td>
                                            <span
                                                className={`usr-badge ${
                                                    student.status === "Activo"
                                                        ? "usr-badge--activo"
                                                        : "usr-badge--suspendido"
                                                }`}
                                            >
                                                <span className="usr-badge-dot"></span>
                                                {student.status}
                                            </span>
                                        </td>

                                        <td style={{ fontSize: "0.8rem", color: "#94a3b8" }}>
                                            {student.lastAccess}
                                        </td>

                                        <td>
                                            <div
                                                className="usr-actions-cell"
                                                style={{ justifyContent: "center" }}
                                            >
                                                <button
                                                    className="usr-action-icon-btn usr-action-icon-btn--view"
                                                    title="Ver perfil"
                                                    type="button"
                                                    onClick={() => handleView(student)}
                                                >
                                                    <i className="fa-solid fa-eye"></i>
                                                </button>
                                                <button
                                                    className="usr-action-icon-btn usr-action-icon-btn--edit"
                                                    title="Editar estudiante"
                                                    type="button"
                                                    onClick={() => handleEdit(student)}
                                                >
                                                    <i className="fa-solid fa-pen-to-square"></i>
                                                </button>
                                                <button
                                                    className="usr-action-icon-btn usr-action-icon-btn--suspend"
                                                    title={
                                                        student.status === "Activo"
                                                            ? "Bloquear / Suspender acceso"
                                                            : "Reactivar acceso"
                                                    }
                                                    type="button"
                                                    disabled={togglingId === student.id}
                                                    onClick={() => handleSuspend(student)}
                                                >
                                                    <i
                                                        className={
                                                            student.status === "Activo"
                                                                ? "fa-solid fa-user-slash"
                                                                : "fa-solid fa-user-check"
                                                        }
                                                    ></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan="6">
                                        <div className="usr-empty-state">
                                            <i className="fa-solid fa-user-slash"></i>
                                            <p>
                                                No se encontraron usuarios con los filtros seleccionados.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="usr-table-footer">
                    <span>Total: {totalCount} usuarios registrados</span>
                    <span>Sincronizado con PostgreSQL</span>
                </div>
            </section>
        </div>
    );
};

export default Users;
