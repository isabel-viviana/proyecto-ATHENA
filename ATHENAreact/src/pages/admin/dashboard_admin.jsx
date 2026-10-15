import "./dashboard_admin.css";

const DashboardAdmin = () => {
    const stats = [
        {
            label: "Total Preguntas ICFES",
            value: "1,420",
            trend: "+32 esta semana",
            isUp: true,
            icon: "fa-solid fa-layer-group",
            iconType: "green",
        },
        {
            label: "Materias Oficiales",
            value: "5",
            trend: "100% Cubiertas",
            isUp: true,
            icon: "fa-solid fa-book-bookmark",
            iconType: "blue",
        },
        {
            label: "Temas Registrados",
            value: "48",
            trend: "Alineados Guía ICFES",
            isUp: true,
            icon: "fa-solid fa-diagram-project",
            iconType: "purple",
        },
        {
            label: "Preguntas por Revisar",
            value: "14",
            trend: "Requieren validación",
            isUp: false,
            icon: "fa-solid fa-clipboard-question",
            iconType: "amber",
        },
    ];

    const subjectsBreakdown = [
        { name: "Matemáticas", count: "340 preguntas", pct: 85 },
        { name: "Lectura Crítica", count: "295 preguntas", pct: 74 },
        { name: "Ciencias Naturales", count: "310 preguntas", pct: 78 },
        { name: "Sociales y Ciudadanas", count: "260 preguntas", pct: 65 },
        { name: "Inglés", count: "215 preguntas", pct: 54 },
    ];

    const recentQuestions = [
        {
            code: "MAT-089",
            subject: "Matemáticas",
            topic: "Funciones y Álgebra",
            difficulty: "Media",
            status: "Publicada",
            author: "Prof. Mendoza",
        },
        {
            code: "LEC-112",
            subject: "Lectura Crítica",
            topic: "Tipología Textual y Tesis",
            difficulty: "Alta",
            status: "Publicada",
            author: "Lic. Herrera",
        },
        {
            code: "NAT-045",
            subject: "Ciencias Naturales",
            topic: "Termodinámica y Química",
            difficulty: "Media",
            status: "En Revisión",
            author: "Prof. Gómez",
        },
        {
            code: "SOC-078",
            subject: "Sociales y Ciudadanas",
            topic: "Constitución de 1991",
            difficulty: "Baja",
            status: "Publicada",
            author: "Lic. Herrera",
        },
        {
            code: "ING-034",
            subject: "Inglés",
            topic: "Comprensión Lectora B1",
            difficulty: "Media",
            status: "En Revisión",
            author: "Prof. Salazar",
        },
    ];

    return (
        <div className="adm-dashboard">
            {/* Banner de Administrador Académico */}
            <div className="adm-hero-card">
                <div className="adm-hero-info">
                    <span className="adm-hero-badge">
                        <i className="fa-solid fa-graduation-cap"></i> Gestión Académica
                    </span>
                    <h1>Panel del Administrador Académico</h1>
                    <p>
                        Control del Banco de Preguntas ICFES, organización de materias y calibración pedagógica.
                    </p>
                </div>
                <div className="adm-hero-actions">
                    <button className="adm-btn-secondary">
                        <i className="fa-solid fa-list-check"></i>
                        <span>Revisar Pendientes</span>
                    </button>
                    <button className="adm-btn-primary">
                        <i className="fa-solid fa-plus"></i>
                        <span>Nueva Pregunta</span>
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
                                <i className={`fa-solid ${stat.isUp ? "fa-circle-check" : "fa-clock"}`}></i>
                                {stat.trend}
                            </span>
                        </div>
                        <div className={`adm-stat-icon-wrap adm-stat-icon--${stat.iconType}`}>
                            <i className={stat.icon}></i>
                        </div>
                    </div>
                ))}
            </div>

            {/* Grilla con Banco de Preguntas y Cobertura por Materias */}
            <div className="adm-grid-2col">
                {/* Preguntas Recientes */}
                <div className="adm-card">
                    <div className="adm-card-header">
                        <h2 className="adm-card-title">
                            <i className="fa-solid fa-file-circle-question"></i>
                            Banco de Preguntas Recientes
                        </h2>
                        <span className="adm-card-badge">Últimas adiciones</span>
                    </div>

                    <div className="adm-table-wrap">
                        <table className="adm-table">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Materia / Tema</th>
                                    <th>Dificultad</th>
                                    <th>Estado</th>
                                    <th>Docente</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recentQuestions.map((q, i) => (
                                    <tr key={i}>
                                        <td>
                                            <span style={{ fontWeight: 700, color: "#22c55e", fontSize: "0.8rem" }}>
                                                {q.code}
                                            </span>
                                        </td>
                                        <td>
                                            <div style={{ fontWeight: 600, color: "#f8fafc" }}>{q.subject}</div>
                                            <div style={{ fontSize: "0.74rem", color: "#64748b" }}>{q.topic}</div>
                                        </td>
                                        <td>
                                            <span style={{ fontSize: "0.78rem", color: "#cbd5e1" }}>
                                                {q.difficulty}
                                            </span>
                                        </td>
                                        <td>
                                            <span
                                                className={`adm-badge ${
                                                    q.status === "Publicada"
                                                        ? "adm-badge--success"
                                                        : "adm-badge--warning"
                                                }`}
                                            >
                                                {q.status}
                                            </span>
                                        </td>
                                        <td style={{ fontSize: "0.76rem", color: "#94a3b8" }}>{q.author}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Cobertura por Materias & Acciones */}
                <div style={{ display: "flex", flexDirection: "column", gap: "20px" }}>
                    <div className="adm-card">
                        <div className="adm-card-header">
                            <h2 className="adm-card-title">
                                <i className="fa-solid fa-chart-simple"></i>
                                Cobertura por Materia ICFES
                            </h2>
                        </div>
                        <div className="adm-subject-list">
                            {subjectsBreakdown.map((s, idx) => (
                                <div key={idx} className="adm-subject-item">
                                    <div className="adm-subject-header">
                                        <span className="adm-subject-name">{s.name}</span>
                                        <span className="adm-subject-count">{s.count}</span>
                                    </div>
                                    <div className="adm-progress-track">
                                        <div
                                            className="adm-progress-fill"
                                            style={{ width: `${s.pct}%` }}
                                        ></div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="adm-card">
                        <div className="adm-card-header">
                            <h2 className="adm-card-title">
                                <i className="fa-solid fa-sliders"></i>
                                Herramientas de Banco
                            </h2>
                        </div>
                        <div className="adm-quick-actions">
                            <button className="adm-quick-btn">
                                <i className="fa-solid fa-book-bookmark"></i>
                                <span className="adm-quick-btn-title">Materias y Temas</span>
                                <span className="adm-quick-btn-desc">Gestionar taxonomía</span>
                            </button>
                            <button className="adm-quick-btn">
                                <i className="fa-solid fa-upload"></i>
                                <span className="adm-quick-btn-title">Carga Masiva</span>
                                <span className="adm-quick-btn-desc">Importar preguntas CSV</span>
                            </button>
                            <button className="adm-quick-btn">
                                <i className="fa-solid fa-check-double"></i>
                                <span className="adm-quick-btn-title">Calibrar Respuestas</span>
                                <span className="adm-quick-btn-desc">Ajustar retroalimentación</span>
                            </button>
                            <button className="adm-quick-btn">
                                <i className="fa-solid fa-robot"></i>
                                <span className="adm-quick-btn-title">Explicaciones IA</span>
                                <span className="adm-quick-btn-desc">Generar pistas automáticas</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default DashboardAdmin;
