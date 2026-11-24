import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import "./dashboard_super.css";
import { getAdminStats } from "../../api/documents";

const SUBJECT_ICONS = {
    1: "fa-solid fa-book-open",
    2: "fa-solid fa-calculator",
    3: "fa-solid fa-flask",
    4: "fa-solid fa-landmark",
    5: "fa-solid fa-earth-americas",
};

const DashboardSuper = () => {
    const navigate = useNavigate();
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");

    const currentDate = new Date().toLocaleDateString("es-CO", {
        weekday: "long",
        year: "numeric",
        month: "long",
        day: "numeric",
    });

    const loadStats = async () => {
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
                    "No se pudieron cargar las métricas ejecutivas de MySQL y Supabase."
            );
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadStats();
    }, []);

    const users = data?.users || { total: 0, active: 0, suspended: 0, by_role: {}, recent_users: [] };
    const catalog = data?.catalog || { total_subjects: 0, total_topics: 0, total_stimuli: 0 };
    const questions = data?.questions || {
        total: 0,
        official_bank: 0,
        by_status: { published: 0, review: 0, draft: 0, archived: 0 },
    };
    const activity = data?.academic_activity || {
        total_exam_attempts: 0,
        completed_exam_attempts: 0,
        total_student_answers: 0,
        overall_accuracy: 0,
        recent_attempts: [],
    };
    const subjectsBreakdown = data?.subjects_breakdown || [];

    const kpis = [
        {
            label: "Total Usuarios (MySQL)",
            value: String(users.total ?? 0),
            variation: `Estudiantes: ${users.by_role?.student || 0} • Admins: ${(users.by_role?.admin || 0) + (users.by_role?.superadmin || 0)} • Soporte: ${users.by_role?.support || 0}`,
            icon: "fa-solid fa-users",
            iconTheme: "green",
            isSafe: false,
        },
        {
            label: "Preguntas en Supabase",
            value: String(questions.total ?? 0),
            variation: `${questions.by_status?.published || 0} publicadas • ${questions.by_status?.review || 0} revisión • ${questions.by_status?.draft || 0} borrador`,
            icon: "fa-solid fa-layer-group",
            iconTheme: "purple",
            isSafe: false,
        },
        {
            label: "Áreas y Temas ICFES",
            value: `${catalog.total_subjects} / ${catalog.total_topics}`,
            variation: `${catalog.total_subjects} áreas • ${catalog.total_topics} temas • ${catalog.total_stimuli || 0} estímulos`,
            icon: "fa-solid fa-book-bookmark",
            iconTheme: "blue",
            isSafe: false,
        },
        {
            label: "Actividad Académica Real",
            value: `${activity.completed_exam_attempts}/${activity.total_exam_attempts}`,
            variation: `${activity.total_student_answers} respuestas (${activity.overall_accuracy}% acierto global)`,
            icon: "fa-solid fa-clipboard-check",
            iconTheme: "green",
            isSafe: true,
        },
    ];

    const liveActivities = [];

    (activity.recent_attempts || []).slice(0, 4).forEach((att) => {
        liveActivities.push({
            title: `${att.student_name} — ${att.title || "Simulacro ICFES"}`,
            time: att.created_at
                ? new Date(att.created_at).toLocaleDateString("es-CO", {
                      month: "short",
                      day: "2-digit",
                  })
                : "Reciente",
            status:
                att.status === "completed"
                    ? `Completado (${att.score_percent ?? 0}%)`
                    : "En progreso",
            statusType: att.status === "completed" ? "success" : "info",
            icon: "fa-solid fa-file-signature",
            iconColor: att.status === "completed" ? "green" : "blue",
        });
    });

    (users.recent_users || []).slice(0, 3).forEach((u) => {
        liveActivities.push({
            title: `Cuenta registrada: ${u.full_name} (${u.role})`,
            time: u.created_at
                ? new Date(u.created_at).toLocaleDateString("es-CO", {
                      month: "short",
                      day: "2-digit",
                  })
                : "Reciente",
            status: u.status,
            statusType: u.status === "Activo" ? "info" : "warning",
            icon: "fa-solid fa-user-check",
            iconColor: "purple",
        });
    });

    const quickActions = [
        {
            title: "Gestionar Usuarios y Roles",
            desc: "Crear cuentas, asignar roles (Admin, Soporte, Super) y controlar acceso",
            icon: "fa-solid fa-users-gear",
            path: "/admin/users",
        },
        {
            title: "Banco de Preguntas ICFES",
            desc: "Crear preguntas manuales, revisar extracciones IA y publicar reactivos",
            icon: "fa-solid fa-layer-group",
            path: "/admin/questions",
        },
        {
            title: "Áreas y Temas Curriculares",
            desc: "Administrar el catálogo oficial de áreas y temas en Supabase",
            icon: "fa-solid fa-book-bookmark",
            path: "/admin/subjects",
        },
        {
            title: "Simulacros y Actividad",
            desc: "Consultar disponibilidad de preguntas por área e intentos de estudiantes",
            icon: "fa-solid fa-file-signature",
            path: "/admin/exams",
        },
    ];

    return (
        <div className="super-dashboard">
            <section className="super-header-exec">
                <div className="super-header-left">
                    <div className="super-header-saludo">
                        Panel Ejecutivo — Super Administrador
                        <span className="super-header-saludo-badge">Control Global</span>
                    </div>
                    <div className="super-header-meta">
                        <span className="super-header-date" style={{ textTransform: "capitalize" }}>
                            <i className="fa-regular fa-calendar"></i>
                            {currentDate}
                        </span>
                    </div>
                </div>

                <div className="super-header-right">
                    <button
                        type="button"
                        onClick={loadStats}
                        className="super-status-pill"
                        style={{ cursor: "pointer", border: "none" }}
                        title="Actualizar métricas en vivo"
                    >
                        <span className="super-pulse-dot"></span>
                        <div style={{ display: "flex", flexDirection: "column", gap: "1px", textAlign: "left" }}>
                            <span className="super-status-text">
                                {loading ? "Sincronizando..." : "MySQL + Supabase Conectados"}
                            </span>
                            <span className="super-status-sub">
                                {users.active} cuentas activas • {questions.by_status?.published || 0} preguntas publicadas
                            </span>
                        </div>
                    </button>
                </div>
            </section>

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
                            <span className="super-kpi-value">{loading ? "..." : kpi.value}</span>
                            <span
                                className={`super-kpi-variation ${
                                    kpi.isSafe ? "super-kpi-variation--safe" : ""
                                }`}
                            >
                                <i className="fa-solid fa-circle-info"></i>
                                {kpi.variation}
                            </span>
                        </div>
                    </div>
                ))}
            </section>

            <section className="super-middle-grid">
                <div className="super-card-panel">
                    <div className="super-panel-header">
                        <h2 className="super-panel-title">
                            <i className="fa-solid fa-wave-square"></i>
                            Actividad Académica y Registros Recientes
                        </h2>
                        <span className="super-panel-tag">
                            <span className="super-panel-tag-dot"></span>
                            Datos Reales
                        </span>
                    </div>

                    <div className="super-activity-feed">
                        {loading ? (
                            <div style={{ padding: "24px", textAlign: "center", color: "#94a3b8" }}>
                                Consultando actividad reciente...
                            </div>
                        ) : liveActivities.length > 0 ? (
                            liveActivities.map((act, index) => (
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
                            ))
                        ) : (
                            <div style={{ padding: "24px", textAlign: "center", color: "#94a3b8" }}>
                                Aún no se registran eventos recientes.
                            </div>
                        )}
                    </div>
                </div>

                <div className="super-card-panel">
                    <div className="super-panel-header">
                        <h2 className="super-panel-title">
                            <i className="fa-solid fa-chart-simple"></i>
                            Banco y Precisión por Área ICFES
                        </h2>
                        <span className="super-panel-tag">Supabase PostgreSQL</span>
                    </div>

                    <div className="super-subjects-stack">
                        {subjectsBreakdown.map((subj) => {
                            const maxQ = Math.max(1, questions.total || 1);
                            const coveragePct = Math.min(
                                100,
                                Math.round(((subj.total_questions || 0) / maxQ) * 100)
                            );
                            const displayPct =
                                subj.accuracy_avg !== null && subj.accuracy_avg !== undefined
                                    ? `${subj.accuracy_avg}% acierto`
                                    : `${subj.published_questions}/${subj.total_questions} pub.`;

                            return (
                                <div key={subj.id} className="super-subject-row">
                                    <div className="super-subject-info">
                                        <span className="super-subject-label">
                                            <i
                                                className={
                                                    SUBJECT_ICONS[subj.id] || "fa-solid fa-book-bookmark"
                                                }
                                            ></i>
                                            {subj.name}{" "}
                                            <span style={{ fontSize: "0.75rem", color: "#64748b" }}>
                                                ({subj.topics_count} temas • {subj.total_questions} preg.)
                                            </span>
                                        </span>
                                        <span className="super-subject-pct">{displayPct}</span>
                                    </div>
                                    <div className="super-bar-container">
                                        <div
                                            className="super-bar-fill"
                                            style={{
                                                width: `${
                                                    subj.accuracy_avg !== null &&
                                                    subj.accuracy_avg !== undefined
                                                        ? subj.accuracy_avg
                                                        : coveragePct
                                                }%`,
                                            }}
                                        ></div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </section>

            <section className="super-quick-actions-panel">
                <h2 className="super-section-heading">
                    <i className="fa-solid fa-bolt"></i>
                    Módulos de Administración Directa
                </h2>

                <div className="super-actions-grid">
                    {quickActions.map((action, idx) => (
                        <button
                            key={idx}
                            className="super-action-btn"
                            type="button"
                            onClick={() => navigate(action.path)}
                        >
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
