import { useState, useMemo } from "react";
import "./users.css";

const initialStudents = [
    {
        id: 1,
        name: "María Alejandra Restrepo",
        email: "maria.restrepo@colegio.edu.co",
        grade: "11° A",
        targetScore: 420,
        status: "Activo",
        lastAccess: "Hoy, 10:24 AM",
        avatarBg: "linear-gradient(135deg, #10b981, #059669)",
    },
    {
        id: 2,
        name: "Juan David Morales",
        email: "juan.morales@colmayor.edu.co",
        grade: "11° B",
        targetScore: 395,
        status: "Activo",
        lastAccess: "Hoy, 09:15 AM",
        avatarBg: "linear-gradient(135deg, #3b82f6, #1d4ed8)",
    },
    {
        id: 3,
        name: "Valentina Gómez Ríos",
        email: "valen.gomez@instituto.edu.co",
        grade: "10° A",
        targetScore: 410,
        status: "Activo",
        lastAccess: "Ayer, 06:40 PM",
        avatarBg: "linear-gradient(135deg, #a855f7, #7e22ce)",
    },
    {
        id: 4,
        name: "Santiago Vargas Peña",
        email: "santiago.vargas@sanignacio.edu.co",
        grade: "11° A",
        targetScore: 380,
        status: "Suspendido",
        lastAccess: "Hace 4 días",
        avatarBg: "linear-gradient(135deg, #ef4444, #b91c1c)",
    },
    {
        id: 5,
        name: "Camila Andrea Torres",
        email: "camila.torres@liceo.edu.co",
        grade: "10° B",
        targetScore: 440,
        status: "Activo",
        lastAccess: "Hoy, 08:30 AM",
        avatarBg: "linear-gradient(135deg, #f59e0b, #d97706)",
    },
    {
        id: 6,
        name: "David Alejandro Gil",
        email: "david.gil@colegionacional.edu.co",
        grade: "11° C",
        targetScore: 365,
        status: "Activo",
        lastAccess: "Ayer, 04:20 PM",
        avatarBg: "linear-gradient(135deg, #06b6d4, #0891b2)",
    },
    {
        id: 7,
        name: "Lucía Fernández Cruz",
        email: "lucia.fernandez@athena.edu.co",
        grade: "11° B",
        targetScore: 450,
        status: "Activo",
        lastAccess: "Hoy, 11:05 AM",
        avatarBg: "linear-gradient(135deg, #ec4899, #be185d)",
    },
    {
        id: 8,
        name: "Sebastián Mendoza Polo",
        email: "sebastian.m@colegio.edu.co",
        grade: "10° A",
        targetScore: 375,
        status: "Suspendido",
        lastAccess: "Hace 1 semana",
        avatarBg: "linear-gradient(135deg, #64748b, #475569)",
    },
    {
        id: 9,
        name: "Isabella Castro Duque",
        email: "isabella.castro@escuela.edu.co",
        grade: "11° A",
        targetScore: 415,
        status: "Activo",
        lastAccess: "Hoy, 07:50 AM",
        avatarBg: "linear-gradient(135deg, #14b8a6, #0f766e)",
    },
    {
        id: 10,
        name: "Mateo Ortiz Herrera",
        email: "mateo.ortiz@sanpedro.edu.co",
        grade: "10° B",
        targetScore: 390,
        status: "Activo",
        lastAccess: "Ayer, 02:10 PM",
        avatarBg: "linear-gradient(135deg, #8b5cf6, #6d28d9)",
    },
];

const Users = () => {
    const [searchTerm, setSearchTerm] = useState("");
    const [statusFilter, setStatusFilter] = useState("Todos");
    const [gradeFilter, setGradeFilter] = useState("Todos");

    // Filtrado interactivo
    const filteredStudents = useMemo(() => {
        return initialStudents.filter((student) => {
            const matchesSearch =
                student.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                student.email.toLowerCase().includes(searchTerm.toLowerCase());

            const matchesStatus =
                statusFilter === "Todos" || student.status === statusFilter;

            const matchesGrade =
                gradeFilter === "Todos" || student.grade.startsWith(gradeFilter);

            return matchesSearch && matchesStatus && matchesGrade;
        });
    }, [searchTerm, statusFilter, gradeFilter]);

    // Métricas del panel de resumen
    const totalCount = initialStudents.length;
    const activeCount = initialStudents.filter((s) => s.status === "Activo").length;
    const suspendedCount = initialStudents.filter((s) => s.status === "Suspendido").length;
    const averageTargetScore = Math.round(
        initialStudents.reduce((acc, s) => acc + s.targetScore, 0) / totalCount
    );

    // Acciones de consola
    const handleView = (student) => {
        console.log("Ver detalles de estudiante:", student);
    };

    const handleEdit = (student) => {
        console.log("Editar estudiante:", student);
    };

    const handleSuspend = (student) => {
        console.log("Acción suspender/reactivar estudiante:", student);
    };

    const handleCreateNew = () => {
        console.log("Abrir modal de creación de nuevo estudiante");
    };

    // Obtener iniciales
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
                    <i className="fa-solid fa-user-plus"></i>
                    <span>+ Nuevo estudiante</span>
                </button>
            </header>

            {/* 2. PANEL RESUMEN (TARJETAS STATS) */}
            <section className="usr-stats-grid">
                <div className="usr-stat-card">
                    <div className="usr-stat-info">
                        <span className="usr-stat-label">Total Estudiantes</span>
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
                        <span className="usr-stat-label">Suspendidos</span>
                        <span className="usr-stat-val">{suspendedCount}</span>
                    </div>
                    <div className="usr-stat-icon-wrap usr-stat-icon--red">
                        <i className="fa-solid fa-user-xmark"></i>
                    </div>
                </div>

                <div className="usr-stat-card">
                    <div className="usr-stat-info">
                        <span className="usr-stat-label">Promedio Objetivo</span>
                        <span className="usr-stat-val">{averageTargetScore} <span style={{ fontSize: "0.9rem", color: "#94a3b8" }}>/ 500</span></span>
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
                            {filteredStudents.length > 0 ? (
                                filteredStudents.map((student) => (
                                    <tr key={student.id}>
                                        {/* Avatar + Nombre + Correo */}
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

                                        {/* Grado */}
                                        <td>
                                            <span className="usr-grade-badge">
                                                {student.grade}
                                            </span>
                                        </td>

                                        {/* Puntaje Objetivo */}
                                        <td>
                                            <span className="usr-score">
                                                {student.targetScore} <span className="usr-score-max">/ 500</span>
                                            </span>
                                        </td>

                                        {/* Estado */}
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

                                        {/* Último Acceso */}
                                        <td style={{ fontSize: "0.8rem", color: "#94a3b8" }}>
                                            {student.lastAccess}
                                        </td>

                                        {/* Acciones */}
                                        <td>
                                            <div className="usr-actions-cell" style={{ justifyContent: "center" }}>
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
                                                    title={student.status === "Activo" ? "Suspender acceso" : "Reactivar acceso"}
                                                    type="button"
                                                    onClick={() => handleSuspend(student)}
                                                >
                                                    <i className={student.status === "Activo" ? "fa-solid fa-user-slash" : "fa-solid fa-user-check"}></i>
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
                                            <p>No se encontraron estudiantes con los filtros seleccionados.</p>
                                        </div>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="usr-table-footer">
                    <span>Página 1 de 1</span>
                    <span>10 registros por página</span>
                </div>
            </section>
        </div>
    );
};

export default Users;
