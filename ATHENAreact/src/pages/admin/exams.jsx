import { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import "./exams.css";
import { getAdminStats } from "../../api/documents";

const SUBJECT_VISUALS = {
    1: { icon: "fa-solid fa-book-open", color: "#3b82f6" },
    2: { icon: "fa-solid fa-calculator", color: "#10b981" },
    3: { icon: "fa-solid fa-flask", color: "#a855f7" },
    4: { icon: "fa-solid fa-landmark", color: "#f59e0b" },
    5: { icon: "fa-solid fa-earth-americas", color: "#06b6d4" },
};

const Exams = () => {
    const navigate = useNavigate();
    const [examType, setExamType] = useState("Completo");
    const [durationMinutes, setDurationMinutes] = useState(120);
    const [subjects, setSubjects] = useState([]);
    const [activity, setActivity] = useState({
        total_exam_attempts: 0,
        completed_exam_attempts: 0,
        in_progress_exam_attempts: 0,
        recent_attempts: [],
    });
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const loadRealExamData = async () => {
            setLoading(true);
            try {
                const res = await getAdminStats();
                if (res?.success && res?.data) {
                    const breakdown = res.data.subjects_breakdown || [];
                    setSubjects(
                        breakdown.map((s) => {
                            const vis = SUBJECT_VISUALS[s.id] || {
                                icon: "fa-solid fa-book-bookmark",
                                color: "#10b981",
                            };
                            return {
                                id: s.id,
                                name: s.name,
                                icon: vis.icon,
                                color: vis.color,
                                publishedQuestions: Number(s.published_questions ?? 0),
                                totalQuestions: Number(s.total_questions ?? 0),
                                selected: Number(s.published_questions ?? 0) > 0,
                            };
                        })
                    );
                    if (res.data.academic_activity) {
                        setActivity(res.data.academic_activity);
                    }
                }
            } catch {
                // Mantiene estado vacío en caso de fallo
            } finally {
                setLoading(false);
            }
        };
        loadRealExamData();
    }, []);

    const toggleSubject = (id) => {
        setSubjects((prev) =>
            prev.map((s) => (s.id === id ? { ...s, selected: !s.selected } : s))
        );
    };

    const handleTypeChange = (type) => {
        setExamType(type);
        if (type === "Completo") {
            setDurationMinutes(120);
        } else if (type === "Mini") {
            setDurationMinutes(25);
        } else if (type === "Por áreas") {
            setDurationMinutes(40);
        }
    };

    const selectedSubjects = useMemo(() => {
        return subjects.filter((s) => s.selected);
    }, [subjects]);

    const totalAvailableQuestions = useMemo(() => {
        if (examType === "Mini") {
            const sumPub = selectedSubjects.reduce((acc, s) => acc + s.publishedQuestions, 0);
            return Math.min(15, sumPub);
        }
        return selectedSubjects.reduce((acc, curr) => {
            const maxPerArea = examType === "Completo" ? 15 : 20;
            return acc + Math.min(maxPerArea, curr.publishedQuestions);
        }, 0);
    }, [selectedSubjects, examType]);

    return (
        <div className="exm-container">
            <header className="exm-header">
                <div className="exm-header-left">
                    <h1 className="exm-title">
                        <i className="fa-solid fa-file-signature"></i>
                        Disponibilidad de Simulacros y Actividad Estudiantil
                    </h1>
                    <p className="exm-subtitle">
                        Supervisa las preguntas publicadas disponibles por área en Supabase y los intentos reales de simulacro.
                    </p>
                </div>
                <button
                    className="exm-btn-save-header"
                    type="button"
                    onClick={() => navigate("/admin/questions")}
                >
                    <i className="fa-solid fa-layer-group"></i>
                    <span>Gestionar Banco de Preguntas</span>
                </button>
            </header>

            <div className="exm-layout-grid">
                <div className="exm-form-column">
                    <div className="exm-panel-card">
                        <h2 className="exm-panel-title">
                            <i className="fa-solid fa-sliders"></i>
                            Modalidades Oficiales de Simulacro (Question Bank)
                        </h2>

                        <div className="exm-form-group">
                            <label className="exm-form-label">Selecciona una modalidad para verificar cobertura</label>
                            <div className="exm-type-pills-grid">
                                <div
                                    className={`exm-type-pill ${
                                        examType === "Completo" ? "exm-type-pill--active" : ""
                                    }`}
                                    onClick={() => handleTypeChange("Completo")}
                                >
                                    <span className="exm-type-pill-title">Completo</span>
                                    <span className="exm-type-pill-desc">
                                        Todas las áreas activas (120 min)
                                    </span>
                                </div>

                                <div
                                    className={`exm-type-pill ${
                                        examType === "Mini" ? "exm-type-pill--active" : ""
                                    }`}
                                    onClick={() => handleTypeChange("Mini")}
                                >
                                    <span className="exm-type-pill-title">Mini Rápido</span>
                                    <span className="exm-type-pill-desc">
                                        15 preguntas balanceadas (25 min)
                                    </span>
                                </div>

                                <div
                                    className={`exm-type-pill ${
                                        examType === "Por áreas" ? "exm-type-pill--active" : ""
                                    }`}
                                    onClick={() => handleTypeChange("Por áreas")}
                                >
                                    <span className="exm-type-pill-title">Por Áreas</span>
                                    <span className="exm-type-pill-desc">
                                        Enfocado por materia (40 min)
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="exm-panel-card">
                        <h2 className="exm-panel-title">
                            <i className="fa-solid fa-book-bookmark"></i>
                            Preguntas Publicadas Disponibles por Área en Supabase
                        </h2>

                        {loading ? (
                            <div style={{ padding: "24px", textAlign: "center", color: "#94a3b8" }}>
                                Consultando banco publicado en Supabase...
                            </div>
                        ) : (
                            <div className="exm-subjects-checklist">
                                {subjects.map((subj) => (
                                    <div
                                        key={subj.id}
                                        className={`exm-subject-check-item ${
                                            subj.selected ? "exm-subject-check-item--checked" : ""
                                        }`}
                                        onClick={() => toggleSubject(subj.id)}
                                    >
                                        <div className="exm-subject-check-left">
                                            <div
                                                className={`exm-custom-checkbox ${
                                                    subj.selected ? "exm-custom-checkbox--checked" : ""
                                                }`}
                                            >
                                                {subj.selected && <i className="fa-solid fa-check"></i>}
                                            </div>
                                            <div
                                                className="exm-subject-icon-box"
                                                style={{
                                                    backgroundColor: `${subj.color}18`,
                                                    color: subj.color,
                                                }}
                                            >
                                                <i className={subj.icon}></i>
                                            </div>
                                            <span className="exm-subject-name">
                                                {subj.name}{" "}
                                                <span style={{ fontSize: "0.75rem", color: "#64748b" }}>
                                                    (ID #{subj.id})
                                                </span>
                                            </span>
                                        </div>

                                        <span className="exm-subject-questions-badge">
                                            {subj.publishedQuestions} publicadas ({subj.totalQuestions} en banco)
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="exm-panel-card">
                        <h2 className="exm-panel-title">
                            <i className="fa-solid fa-clock-rotate-left"></i>
                            Últimos Simulacros Presentados por Estudiantes ({activity.total_exam_attempts} totales)
                        </h2>

                        {(activity.recent_attempts || []).length > 0 ? (
                            <div style={{ display: "flex", flexDirection: "column", gap: "10px" }}>
                                {activity.recent_attempts.map((att) => (
                                    <div
                                        key={att.id}
                                        style={{
                                            display: "flex",
                                            justifyContent: "space-between",
                                            alignItems: "center",
                                            padding: "10px 14px",
                                            borderRadius: "10px",
                                            background: "rgba(15, 23, 42, 0.65)",
                                            border: "1px solid rgba(148, 163, 184, 0.16)",
                                        }}
                                    >
                                        <div>
                                            <div style={{ fontWeight: 600, color: "#f8fafc", fontSize: "0.9rem" }}>
                                                {att.student_name} — {att.title || "Simulacro ICFES"}
                                            </div>
                                            <div style={{ fontSize: "0.76rem", color: "#94a3b8" }}>
                                                Modalidad: {att.exam_type} • Preguntas: {att.correct_count}/{att.total_questions}
                                            </div>
                                        </div>
                                        <div style={{ textAlign: "right" }}>
                                            <div
                                                style={{
                                                    fontWeight: 700,
                                                    fontSize: "0.85rem",
                                                    color:
                                                        att.status === "completed" ? "#34d399" : "#fbbf24",
                                                }}
                                            >
                                                {att.status === "completed"
                                                    ? `${att.score_percent ?? 0}%`
                                                    : "En curso"}
                                            </div>
                                            <div style={{ fontSize: "0.72rem", color: "#64748b" }}>
                                                {att.created_at
                                                    ? new Date(att.created_at).toLocaleDateString("es-CO")
                                                    : ""}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div style={{ color: "#94a3b8", fontSize: "0.85rem" }}>
                                Aún no se registran intentos de simulacro en la base de datos.
                            </div>
                        )}
                    </div>
                </div>

                <aside className="exm-summary-panel">
                    <div className="exm-summary-header">
                        <h3 className="exm-summary-title">
                            <i className="fa-solid fa-clipboard-list"></i>
                            Cobertura de la Modalidad
                        </h3>
                        <span className="exm-summary-tag">Datos Reales</span>
                    </div>

                    <div className="exm-summary-list">
                        <div className="exm-summary-item">
                            <span className="exm-summary-label">
                                <i className="fa-solid fa-tag"></i> Modalidad:
                            </span>
                            <span className="exm-summary-value">{examType}</span>
                        </div>

                        <div className="exm-summary-item">
                            <span className="exm-summary-label">
                                <i className="fa-solid fa-circle-question"></i> Preguntas por intento:
                            </span>
                            <span className="exm-summary-value exm-summary-value--highlight">
                                {totalAvailableQuestions}
                            </span>
                        </div>

                        <div className="exm-summary-item">
                            <span className="exm-summary-label">
                                <i className="fa-solid fa-clock"></i> Duración oficial:
                            </span>
                            <span className="exm-summary-value">{durationMinutes} min</span>
                        </div>

                        <div className="exm-summary-item">
                            <span className="exm-summary-label">
                                <i className="fa-solid fa-check-double"></i> Intentos completados:
                            </span>
                            <span className="exm-summary-value">
                                {activity.completed_exam_attempts} / {activity.total_exam_attempts}
                            </span>
                        </div>
                    </div>

                    <div className="exm-summary-subjects-box">
                        <span className="exm-summary-subjects-title">
                            Áreas Seleccionadas ({selectedSubjects.length} de {subjects.length})
                        </span>

                        {selectedSubjects.length > 0 ? (
                            <div className="exm-summary-chips">
                                {selectedSubjects.map((s) => (
                                    <span key={s.id} className="exm-summary-chip">
                                        <span
                                            className="exm-summary-chip-dot"
                                            style={{ backgroundColor: s.color }}
                                        ></span>
                                        {s.name} ({s.publishedQuestions})
                                    </span>
                                ))}
                            </div>
                        ) : (
                            <span style={{ fontSize: "0.78rem", color: "#ef4444" }}>
                                <i className="fa-solid fa-triangle-exclamation"></i> Selecciona al menos un área.
                            </span>
                        )}
                    </div>

                    <button
                        className="exm-btn-save-bottom"
                        type="button"
                        onClick={() => navigate("/admin/questions")}
                    >
                        <i className="fa-solid fa-arrow-right"></i>
                        <span>Ir a Publicar más Preguntas</span>
                    </button>
                </aside>
            </div>
        </div>
    );
};

export default Exams;
