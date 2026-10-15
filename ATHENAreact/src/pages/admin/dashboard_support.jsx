import "./dashboard_admin.css";

const DashboardSupport = () => {
    const stats = [
        {
            label: "Tickets Abiertos",
            value: "8",
            trend: "3 Alta prioridad",
            isUp: false,
            icon: "fa-solid fa-headset",
            iconType: "green",
        },
        {
            label: "Tiempo Promedio de Respuesta",
            value: "18 min",
            trend: "-4 min que ayer",
            isUp: true,
            icon: "fa-solid fa-clock-rotate-left",
            iconType: "blue",
        },
        {
            label: "Reportes de Preguntas",
            value: "5",
            trend: "Revisión requerida",
            isUp: false,
            icon: "fa-solid fa-triangle-exclamation",
            iconType: "amber",
        },
        {
            label: "Tasa de Resolución",
            value: "96.4%",
            trend: "Excelente desempeño",
            isUp: true,
            icon: "fa-solid fa-circle-check",
            iconType: "purple",
        },
    ];

    const supportTickets = [
        {
            id: "TK-2041",
            user: "Valentina Ríos",
            subject: "Error al cargar gráfico en pregunta #112",
            priority: "Alta",
            status: "Abierto",
            time: "Hace 12 min",
        },
        {
            id: "TK-2040",
            user: "Santiago Morales",
            subject: "Duda con puntaje en Simulacro General",
            priority: "Media",
            status: "En Proceso",
            time: "Hace 45 min",
        },
        {
            id: "TK-2039",
            user: "Camila Torres",
            subject: "Solicitud de restablecimiento de contraseña",
            priority: "Baja",
            status: "Resuelto",
            time: "Hace 2 horas",
        },
        {
            id: "TK-2038",
            user: "Juan Pablo Vargas",
            subject: "No reproduce audio en módulo de Inglés",
            priority: "Alta",
            status: "En Proceso",
            time: "Hace 3 horas",
        },
        {
            id: "TK-2037",
            user: "Mariana Restrepo",
            subject: "Pregunta duplicada en simulacro rápido",
            priority: "Media",
            status: "Resuelto",
            time: "Hace 5 horas",
        },
    ];

    const reportedQuestions = [
        { code: "LEC-089", reason: "Texto con tipografía cortada", reporter: "Valentina R.", date: "Hoy, 10:15 AM" },
        { code: "MAT-042", reason: "Opción C y D tienen el mismo valor", reporter: "David G.", date: "Hoy, 09:30 AM" },
        { code: "NAT-101", reason: "Imagen de la célula desactualizada", reporter: "Sebastián G.", date: "Ayer, 05:20 PM" },
    ];

    return (
        <div className="adm-dashboard">
            {/* Banner de Soporte Técnico */}
            <div className="adm-hero-card">
                <div className="adm-hero-info">
                    <span className="adm-hero-badge">
                        <i className="fa-solid fa-headset"></i> Mesa de Ayuda
                    </span>
                    <h1>Panel de Soporte y Atención Técnica</h1>
                    <p>
                        Gestión de incidencias estudiantiles, atención de tickets y validación de reportes académicos.
                    </p>
                </div>
                <div className="adm-hero-actions">
                    <button className="adm-btn-secondary">
                        <i className="fa-solid fa-flag"></i>
                        <span>Ver Reportes</span>
                    </button>
                    <button className="adm-btn-primary">
                        <i className="fa-solid fa-ticket"></i>
                        <span>Nuevo Ticket</span>
                    </button>
                </div>
            </div>

            {/* Cuadrícula de Métricas Clave */}
            <div className="adm-stats-grid">
                {stats.map((stat, idx) => (
                    <div key={idx} className="adm-stat-card">
                        <div className="adm-stat-info">
                            <span className="adm-stat-label">{stat.label}</span>
                            <span className="adm-stat-value">{stat.value}</span>
                            <span className={`adm-stat-trend ${stat.isUp ? "adm-stat-trend--up" : "adm-stat-trend--down"}`}>
                                <i className={`fa-solid ${stat.isUp ? "fa-arrow-trend-up" : "fa-circle-exclamation"}`}></i>
                                {stat.trend}
                            </span>
                        </div>
                        <div className={`adm-stat-icon-wrap adm-stat-icon--${stat.iconType}`}>
                            <i className={stat.icon}></i>
                        </div>
                    </div>
                ))}
            </div>

            {/* Grilla Principal de Tickets y Reportes */}
            <div className="adm-grid-2col">
                {/* Tabla de Tickets de Soporte */}
                <div className="adm-card">
                    <div className="adm-card-header">
                        <h2 className="adm-card-title">
                            <i className="fa-solid fa-comments-dollar"></i>
                            Tickets Recientes de Estudiantes
                        </h2>
                        <span className="adm-card-badge">Activos</span>
                    </div>

                    <div className="adm-table-wrap">
                        <table className="adm-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Estudiante / Asunto</th>
                                    <th>Prioridad</th>
                                    <th>Estado</th>
                                    <th>Tiempo</th>
                                </tr>
                            </thead>
                            <tbody>
                                {supportTickets.map((t, i) => (
                                    <tr key={i}>
                                        <td>
                                            <span style={{ fontWeight: 700, color: "#38bdf8", fontSize: "0.78rem" }}>
                                                {t.id}
                                            </span>
                                        </td>
                                        <td>
                                            <div style={{ fontWeight: 600, color: "#f8fafc" }}>{t.user}</div>
                                            <div style={{ fontSize: "0.74rem", color: "#64748b" }}>{t.subject}</div>
                                        </td>
                                        <td>
                                            <span
                                                className={`adm-badge ${
                                                    t.priority === "Alta"
                                                        ? "adm-badge--danger"
                                                        : t.priority === "Media"
                                                        ? "adm-badge--warning"
                                                        : "adm-badge--info"
                                                }`}
                                            >
                                                {t.priority}
                                            </span>
                                        </td>
                                        <td>
                                            <span
                                                className={`adm-badge ${
                                                    t.status === "Resuelto"
                                                        ? "adm-badge--success"
                                                        : t.status === "En Proceso"
                                                        ? "adm-badge--info"
                                                        : "adm-badge--warning"
                                                }`}
                                            >
                                                {t.status}
                                            </span>
                                        </td>
                                        <td style={{ fontSize: "0.76rem", color: "#94a3b8" }}>{t.time}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Reportes de Preguntas y Acciones Rápidas */}
                <div style={{ display: "flex", flexDirection: "column", gap: "20px" }}>
                    <div className="adm-card">
                        <div className="adm-card-header">
                            <h2 className="adm-card-title">
                                <i className="fa-solid fa-triangle-exclamation"></i>
                                Reportes de Preguntas ICFES
                            </h2>
                            <span className="adm-card-badge">3 Pendientes</span>
                        </div>
                        <div className="adm-feed-list">
                            {reportedQuestions.map((q, idx) => (
                                <div key={idx} className="adm-feed-item">
                                    <div
                                        className="adm-feed-icon"
                                        style={{ backgroundColor: "rgba(245, 158, 11, 0.15)", color: "#f59e0b" }}
                                    >
                                        <i className="fa-solid fa-flag"></i>
                                    </div>
                                    <div className="adm-feed-body">
                                        <div className="adm-feed-title" style={{ display: "flex", justifyContent: "space-between" }}>
                                            <span style={{ color: "#22c55e" }}>{q.code}</span>
                                            <span style={{ fontSize: "0.7rem", color: "#64748b" }}>{q.date}</span>
                                        </div>
                                        <div style={{ fontSize: "0.76rem", color: "#cbd5e1" }}>{q.reason}</div>
                                        <div style={{ fontSize: "0.7rem", color: "#64748b", marginTop: "2px" }}>
                                            Reportado por: {q.reporter}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="adm-card">
                        <div className="adm-card-header">
                            <h2 className="adm-card-title">
                                <i className="fa-solid fa-wrench"></i>
                                Herramientas de Asistencia
                            </h2>
                        </div>
                        <div className="adm-quick-actions">
                            <button className="adm-quick-btn">
                                <i className="fa-solid fa-key"></i>
                                <span className="adm-quick-btn-title">Desbloquear Cuenta</span>
                                <span className="adm-quick-btn-desc">Reactivar estudiante</span>
                            </button>
                            <button className="adm-quick-btn">
                                <i className="fa-solid fa-envelope-circle-check"></i>
                                <span className="adm-quick-btn-title">Reenviar Acceso</span>
                                <span className="adm-quick-btn-desc">Correo de credenciales</span>
                            </button>
                            <button className="adm-quick-btn">
                                <i className="fa-solid fa-circle-question"></i>
                                <span className="adm-quick-btn-title">FAQ de Soporte</span>
                                <span className="adm-quick-btn-desc">Base de conocimiento</span>
                            </button>
                            <button className="adm-quick-btn">
                                <i className="fa-solid fa-bullhorn"></i>
                                <span className="adm-quick-btn-title">Alerta de Mantenimiento</span>
                                <span className="adm-quick-btn-desc">Programar aviso</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default DashboardSupport;
