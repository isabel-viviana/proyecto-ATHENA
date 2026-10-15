import "./dashboard_super.css";

const DashboardSuper = () => {
    // 1. Header ejecutivo data estática
    const currentDate = "Miércoles, 26 de Agosto de 2026";

    // 2. Tarjetas KPI (4)
    const kpis = [
        {
            label: "Usuarios activos",
            value: "18,420",
            variation: "+14.8% este mes",
            icon: "fa-solid fa-users",
            iconTheme: "green",
            isSafe: false,
        },
        {
            label: "Simulacros hoy",
            value: "6,812",
            variation: "+22.4% vs ayer",
            icon: "fa-solid fa-clipboard-check",
            iconTheme: "blue",
            isSafe: false,
        },
        {
            label: "Preguntas ICFES",
            value: "5,420",
            variation: "+180 agregadas",
            icon: "fa-solid fa-layer-group",
            iconTheme: "purple",
            isSafe: false,
        },
        {
            label: "Errores críticos",
            value: "3",
            variation: "-66% resueltos",
            icon: "fa-solid fa-shield-halved",
            iconTheme: "danger",
            isSafe: true,
        },
    ];

    // 3. Actividad en vivo (6 eventos con hora, icono y estado)
    const liveActivities = [
        {
            title: "María terminó un simulacro",
            time: "Hace 3 min",
            status: "Completado",
            statusType: "success",
            icon: "fa-solid fa-graduation-cap",
            iconColor: "green",
        },
        {
            title: "28 nuevos registros de estudiantes",
            time: "Hace 12 min",
            status: "Registrados",
            statusType: "info",
            icon: "fa-solid fa-user-plus",
            iconColor: "blue",
        },
        {
            title: "Nueva pregunta agregada (Matemáticas)",
            time: "Hace 25 min",
            status: "Publicada",
            statusType: "success",
            icon: "fa-solid fa-file-circle-plus",
            iconColor: "purple",
        },
        {
            title: "Error IA recuperado automáticamente",
            time: "Hace 40 min",
            status: "Auto-recuperado",
            statusType: "warning",
            icon: "fa-solid fa-robot",
            iconColor: "amber",
        },
        {
            title: "Copia de respaldo en la nube finalizada",
            time: "Hace 1 hora",
            status: "Completado",
            statusType: "success",
            icon: "fa-solid fa-database",
            iconColor: "cyan",
        },
        {
            title: "Simulacro Masivo Calificado (Grado 11-A)",
            time: "Hace 2 horas",
            status: "Calificado",
            statusType: "info",
            icon: "fa-solid fa-chart-simple",
            iconColor: "blue",
        },
    ];

    // 4. Rendimiento por materia (5 barras horizontales)
    const subjectsPerformance = [
        { name: "Matemáticas", percentage: 82, icon: "fa-solid fa-calculator" },
        { name: "Lectura Crítica", percentage: 76, icon: "fa-solid fa-book-open" },
        { name: "Ciencias Naturales", percentage: 71, icon: "fa-solid fa-flask" },
        { name: "Sociales y Ciudadanas", percentage: 69, icon: "fa-solid fa-landmark" },
        { name: "Inglés", percentage: 88, icon: "fa-solid fa-earth-americas" },
    ];

    // 5. Acciones rápidas (4 botones grandes)
    const quickActions = [
        {
            title: "Crear estudiante",
            desc: "Registrar y asignar rol académico a un nuevo usuario",
            icon: "fa-solid fa-user-plus",
        },
        {
            title: "Agregar pregunta",
            desc: "Crear y catalogar nuevo reactivo en el banco ICFES",
            icon: "fa-solid fa-plus",
        },
        {
            title: "Enviar notificación",
            desc: "Emitir alertas globales o avisos de mantenimiento",
            icon: "fa-solid fa-bullhorn",
        },
        {
            title: "Ver analítica",
            desc: "Consultar métricas avanzadas y reportes de desempeño",
            icon: "fa-solid fa-chart-pie",
        },
    ];

    return (
        <div className="super-dashboard">
            {/* 1. HEADER EJECUTIVO */}
            <section className="super-header-exec">
                <div className="super-header-left">
                    <div className="super-header-saludo">
                        Bienvenido, Super Administrador
                        <span className="super-header-saludo-badge">Centro de Operaciones</span>
                    </div>
                    <div className="super-header-meta">
                        <span className="super-header-date">
                            <i className="fa-regular fa-calendar"></i>
                            {currentDate}
                        </span>
                    </div>
                </div>

                <div className="super-header-right">
                    <div className="super-status-pill">
                        <span className="super-pulse-dot"></span>
                        <div style={{ display: "flex", flexDirection: "column", gap: "1px" }}>
                            <span className="super-status-text">Sistema operativo</span>
                            <span className="super-status-sub">99.98% Uptime • 14ms</span>
                        </div>
                    </div>
                </div>
            </section>

            {/* 2. TARJETAS KPI (4) */}
            <section className="super-kpis-grid">
                {kpis.map((kpi, idx) => (
                    <div key={idx} className="super-kpi-card">
                        <div className="super-kpi-top">
                            <span className="super-kpi-label">{kpi.label}</span>
                            <div className={`super-kpi-icon-box super-kpi-icon-box--${kpi.iconTheme}`}>
                                <i className={kpi.icon}></i>
                            </div>
                        </div>
                        <div className="super-kpi-body">
                            <span className="super-kpi-value">{kpi.value}</span>
                            <span
                                className={`super-kpi-variation ${
                                    kpi.isSafe ? "super-kpi-variation--safe" : ""
                                }`}
                            >
                                <i className="fa-solid fa-arrow-trend-up"></i>
                                {kpi.variation}
                            </span>
                        </div>
                    </div>
                ))}
            </section>

            {/* 3 & 4. SECCIÓN MEDIA: ACTIVIDAD EN VIVO Y RENDIMIENTO POR MATERIA */}
            <section className="super-middle-grid">
                {/* 3. ACTIVIDAD EN VIVO */}
                <div className="super-card-panel">
                    <div className="super-panel-header">
                        <h2 className="super-panel-title">
                            <i className="fa-solid fa-wave-square"></i>
                            Actividad en vivo
                        </h2>
                        <span className="super-panel-tag">
                            <span className="super-panel-tag-dot"></span>
                            Feed en tiempo real
                        </span>
                    </div>

                    <div className="super-activity-feed">
                        {liveActivities.map((act, index) => (
                            <div key={index} className="super-activity-item">
                                <div className="super-activity-left">
                                    <div
                                        className={`super-activity-icon super-activity-icon--${act.iconColor}`}
                                    >
                                        <i className={act.icon}></i>
                                    </div>
                                    <span className="super-activity-title">{act.title}</span>
                                </div>
                                <div className="super-activity-right">
                                    <span
                                        className={`super-activity-badge super-activity-badge--${act.statusType}`}
                                    >
                                        {act.status}
                                    </span>
                                    <span className="super-activity-time">{act.time}</span>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* 4. RENDIMIENTO POR MATERIA */}
                <div className="super-card-panel">
                    <div className="super-panel-header">
                        <h2 className="super-panel-title">
                            <i className="fa-solid fa-chart-simple"></i>
                            Rendimiento por materia
                        </h2>
                        <span className="super-panel-tag">Promedio ICFES</span>
                    </div>

                    <div className="super-subjects-stack">
                        {subjectsPerformance.map((subj, index) => (
                            <div key={index} className="super-subject-row">
                                <div className="super-subject-info">
                                    <span className="super-subject-label">
                                        <i className={subj.icon}></i>
                                        {subj.name}
                                    </span>
                                    <span className="super-subject-pct">{subj.percentage}%</span>
                                </div>
                                <div className="super-bar-container">
                                    <div
                                        className="super-bar-fill"
                                        style={{ width: `${subj.percentage}%` }}
                                    ></div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* 5. ACCIONES RÁPIDAS */}
            <section className="super-quick-actions-panel">
                <h2 className="super-section-heading">
                    <i className="fa-solid fa-bolt"></i>
                    Acciones Rápidas
                </h2>

                <div className="super-actions-grid">
                    {quickActions.map((action, idx) => (
                        <button key={idx} className="super-action-btn" type="button">
                            <div className="super-action-btn-header">
                                <div className="super-action-icon-wrap">
                                    <i className={action.icon}></i>
                                </div>
                                <i className="fa-solid fa-arrow-right super-action-arrow"></i>
                            </div>
                            <div className="super-action-content">
                                <span className="super-action-title">{action.title}</span>
                                <span className="super-action-desc">{action.desc}</span>
                            </div>
                        </button>
                    ))}
                </div>
            </section>
        </div>
    );
};

export default DashboardSuper;
