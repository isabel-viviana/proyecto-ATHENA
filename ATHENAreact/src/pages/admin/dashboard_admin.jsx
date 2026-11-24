import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import "./dashboard_admin.css";
import { getAdminStats } from "../../api/documents";

const DIFF_LABELS = {
    easy: "Fácil",
    medium: "Media",
    hard: "Alta",
};

const STATUS_LABELS = {
    published: "Publicada",
    review: "Aprobada (Revisión)",
    draft: "Borrador",
    archived: "Archivada",
};

const DashboardAdmin = () => {
    const navigate = useNavigate();
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");

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
                    "No se pudieron consultar las estadísticas académicas desde Supabase."
            );
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadStats();
    }, []);

    const questions = data?.questions || {
        total: 0,
        official_bank: 0,
        by_status: { published: 0, review: 0, draft: 0, archived: 0 },
        by_key_status: { verified: 0 },
    };
    const catalog = data?.catalog || { total_subjects: 0, total_topics: 0 };
    const users = data?.users || { by_role: { student: 0 } };
    const activity = data?.academic_activity || {
        total_exam_attempts: 0,
        total_student_answers: 0,
        overall_accuracy: 0,
    };
    const subjectsBreakdown = data?.subjects_breakdown || [];
    const recentQuestions = data?.recent_questions || [];

    const stats = [
        {
            label: "Total Preguntas ICFES",
            value: loading ? "..." : String(questions.total ?? 0),
            trend: `${questions.by_status?.published || 0} publicadas • ${questions.by_status?.review || 0} aprobadas`,
            isUp: true,
            icon: "fa-solid fa-layer-group",
            iconType: "green",
        },
        {
            label: "Áreas Académicas",
            value: loading ? "..." : String(catalog.total_subjects ?? 0),
            trend: `${questions.by_key_status?.verified || 0} claves verificadas`,
            isUp: true,
            icon: "fa-solid fa-book-bookmark",
            iconType: "blue",
        },
        {
            label: "Temas Registrados",
            value: loading ? "..." : String(catalog.total_topics ?? 0),
            trend: `${catalog.active_topics ?? 0} activos en Supabase`,
            isUp: true,
            icon: "fa-solid fa-diagram-project",
            iconType: "purple",
        },
        {
            label: "Borradores / Por Revisar",
            value: loading ? "..." : String(questions.by_status?.draft ?? 0),
            trend: `${users.by_role?.student || 0} estudiantes • ${activity.total_exam_attempts || 0} simulacros`,
            isUp: (questions.by_status?.draft || 0) === 0,
            icon: "fa-solid fa-clipboard-question",
            iconType: "amber",
        },
    ];

    return (
        <div className="adm-dashboard">
            <div className="adm-hero-card">
                <div className="adm-hero-info">
                    <span className="adm-hero-badge">
                        <i className="fa-solid fa-graduation-cap"></i> Gestión Académica
                    </span>
                    <h1>Panel del Administrador Académico</h1>
                    <p>
                        Control real del Banco de Preguntas ICFES en Supabase, áreas curriculares, temas y seguimiento de estudiantes.
                    </p>
                </div>
                <div className="adm-hero-actions">
                    <button
                        type="button"
                        className="adm-btn-secondary"
                        onClick={() => navigate("/admin/questions")}
                    >
                        <i className="fa-solid fa-list-check"></i>
                        <span>Revisar Banco ({questions.by_status?.draft || 0} borradores)</span>
                    </button>
                    <button
                        type="button"
                        className="adm-btn-primary"
                        onClick={() => navigate("/admin/questions")}
                    >
                        <i className="fa-solid fa-plus"></i>
                        <span>Nueva Pregunta</span>
                    </button>
                </div>
            </div>

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
                                        stat.isUp ? "fa-circle-check" : "fa-clock"
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
                            <i className="fa-solid fa-file-circle-question"></i>
                            Preguntas Recientes en Supabase
                        </h2>
                        <span className="adm-card-badge">Últimas {recentQuestions.length}</span>
                    </div>

                    <div className="adm-table-wrap">
                        <table className="adm-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Área / Tema</th>
                                    <th>Dificultad</th>
                                    <th>Estado</th>
                                    <th>Clave</th>
                                </tr>
                            </thead>
                            <tbody>
                                {loading ? (
                                    <tr>
                                        <td colSpan="5" style={{ textAlign: "center", padding: "24px", color: "#94a3b8" }}>
                                            Consultando preguntas recientes...
                                        </td>
                                    </tr>
                                ) : recentQuestions.length > 0 ? (
                                    recentQuestions.map((q) => (
                                        <tr
                                            key={q.id}
                                            style={{ cursor: "pointer" }}
                                            onClick={() => navigate("/admin/questions")}
                                        >
                                            <td>
                                                <span
                                                    style={{
                                                        fontWeight: 700,
                                                        color: "#22c55e",
                                                        fontSize: "0.78rem",
                                                        fontFamily: "monospace",
                                                    }}
                                                >
                                                    {String(q.id).slice(0, 8)}
                                                </span>
                                            </td>
                                            <td>
                                                <div style={{ fontWeight: 600, color: "#f8fafc" }}>
                                                    {q.subject_name}
                                                </div>
                                                <div style={{ fontSize: "0.74rem", color: "#64748b" }}>
                                                    {q.topic_name || "Sin tema asignado"}
                                                </div>
                                            </td>
                                            <td>
                                                <span style={{ fontSize: "0.78rem", color: "#cbd5e1" }}>
                                                    {DIFF_LABELS[q.difficulty] || q.difficulty}
                                                </span>
                                            </td>
                                            <td>
                                                <span
                                                    className={`adm-badge ${
                                                        q.status === "published"
                                                            ? "adm-badge--success"
                                                            : q.status === "review"
                                                            ? "adm-badge--info"
                                                            : "adm-badge--warning"
                                                    }`}
                                                >
                                                    {STATUS_LABELS[q.status] || q.status}
                                                </span>
                                            </td>
                                            <td style={{ fontSize: "0.76rem", color: "#94a3b8" }}>
                                                {q.key_status === "verified" ? "Verificada" : q.key_status}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan="5" style={{ textAlign: "center", padding: "24px", color: "#94a3b8" }}>
                                            No hay preguntas registradas.
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
                                <i className="fa-solid fa-chart-simple"></i>
                                Cobertura Real por Materia ICFES
                            </h2>
                        </div>
                        <div className="adm-subject-list">
                            {subjectsBreakdown.map((s) => {
                                const totalInSubject = s.total_questions || 0;
                                const pubInSubject = s.published_questions || 0;
                                const pct =
                                    totalInSubject > 0
                                        ? Math.round((pubInSubject / totalInSubject) * 100)
                                        : 0;
                                return (
                                    <div key={s.id} className="adm-subject-item">
                                        <div className="adm-subject-header">
                                            <span className="adm-subject-name">
                                                {s.name}{" "}
                                                <span style={{ fontSize: "0.73rem", color: "#64748b" }}>
                                                    ({s.topics_count} temas)
                                                </span>
                                            </span>
                                            <span className="adm-subject-count">
                                                {pubInSubject} pub. / {totalInSubject} total
                                            </span>
                                        </div>
                                        <div className="adm-progress-track">
                                            <div
                                                className="adm-progress-fill"
                                                style={{ width: `${pct}%` }}
                                            ></div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    <div className="adm-card">
                        <div className="adm-card-header">
                            <h2 className="adm-card-title">
                                <i className="fa-solid fa-sliders"></i>
                                Herramientas Académicas
                            </h2>
                        </div>
                        <div className="adm-quick-actions">
                            <button
                                type="button"
                                className="adm-quick-btn"
                                onClick={() => navigate("/admin/subjects")}
                            >
                                <i className="fa-solid fa-book-bookmark"></i>
                                <span className="adm-quick-btn-title">Materias y Temas</span>
                                <span className="adm-quick-btn-desc">Gestionar taxonomía en Supabase</span>
                            </button>
                            <button
                                type="button"
                                className="adm-quick-btn"
                                onClick={() => navigate("/admin/questions")}
                            >
                                <i className="fa-solid fa-layer-group"></i>
                                <span className="adm-quick-btn-title">Banco de Preguntas</span>
                                <span className="adm-quick-btn-desc">Crear y calibrar reactivos</span>
                            </button>
                            <button
                                type="button"
                                className="adm-quick-btn"
                                onClick={() => navigate("/admin/support")}
                            >
                                <i className="fa-solid fa-headset"></i>
                                <span className="adm-quick-btn-title">Soporte y Auditoría</span>
                                <span className="adm-quick-btn-desc">Verificación y alertas operativas</span>
                            </button>
                            <button
                                type="button"
                                className="adm-quick-btn"
                                onClick={() => navigate("/admin/exams")}
                            >
                                <i className="fa-solid fa-file-signature"></i>
                                <span className="adm-quick-btn-title">Simulacros</span>
                                <span className="adm-quick-btn-desc">Disponibilidad por área</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default DashboardAdmin;
