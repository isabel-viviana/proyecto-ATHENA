import { useState, useMemo } from "react";
import "./exams.css";

const defaultSubjects = [
    {
        id: "mat",
        name: "Matemáticas",
        icon: "fa-solid fa-calculator",
        color: "#10b981",
        questionsFull: 50,
        questionsMini: 12,
        selected: true,
    },
    {
        id: "lec",
        name: "Lectura Crítica",
        icon: "fa-solid fa-book-open",
        color: "#3b82f6",
        questionsFull: 41,
        questionsMini: 10,
        selected: true,
    },
    {
        id: "nat",
        name: "Ciencias Naturales",
        icon: "fa-solid fa-flask",
        color: "#a855f7",
        questionsFull: 58,
        questionsMini: 14,
        selected: true,
    },
    {
        id: "soc",
        name: "Sociales y Ciudadanas",
        icon: "fa-solid fa-landmark",
        color: "#f59e0b",
        questionsFull: 50,
        questionsMini: 12,
        selected: true,
    },
    {
        id: "ing",
        name: "Inglés",
        icon: "fa-solid fa-earth-americas",
        color: "#06b6d4",
        questionsFull: 55,
        questionsMini: 12,
        selected: true,
    },
];

const Exams = () => {
    // 2. Configuración Estados
    const [examName, setExamName] = useState("Simulacro Diagnóstico Nacional ICFES 2026");
    const [examType, setExamType] = useState("Completo"); // "Completo", "Mini", "Por áreas"
    const [difficulty, setDifficulty] = useState("Media"); // "Fácil", "Media", "Difícil", "Adaptativa"
    const [durationMinutes, setDurationMinutes] = useState(270);

    // 3. Materias Checkboxes
    const [subjects, setSubjects] = useState(defaultSubjects);

    // Estado visual de guardado exitoso
    const [savedNotice, setSavedNotice] = useState(false);

    // Manejador del checkbox de materias
    const toggleSubject = (id) => {
        setSubjects((prev) =>
            prev.map((s) => (s.id === id ? { ...s, selected: !s.selected } : s))
        );
    };

    // Cambiar tipo de examen actualizando tiempos por defecto
    const handleTypeChange = (type) => {
        setExamType(type);
        if (type === "Completo") {
            setDurationMinutes(270);
        } else if (type === "Mini") {
            setDurationMinutes(75);
        } else if (type === "Por áreas") {
            setDurationMinutes(120);
        }
    };

    // 4. Resumen Lateral dinámico
    const selectedSubjects = useMemo(() => {
        return subjects.filter((s) => s.selected);
    }, [subjects]);

    const totalQuestionsCount = useMemo(() => {
        return selectedSubjects.reduce((acc, curr) => {
            if (examType === "Mini") {
                return acc + curr.questionsMini;
            }
            return acc + curr.questionsFull;
        }, 0);
    }, [selectedSubjects, examType]);

    // Guardar simulacro (solo visual con feedback)
    const handleSaveExam = (e) => {
        if (e) e.preventDefault();
        const payload = {
            name: examName,
            type: examType,
            difficulty,
            durationMinutes,
            selectedSubjects: selectedSubjects.map((s) => s.name),
            totalQuestions: totalQuestionsCount,
        };

        console.log("Simulacro configurado exitosamente:", payload);
        setSavedNotice(true);
        setTimeout(() => {
            setSavedNotice(false);
        }, 4000);
    };

    return (
        <div className="exm-container">
            {/* 1. HEADER */}
            <header className="exm-header">
                <div className="exm-header-left">
                    <h1 className="exm-title">
                        <i className="fa-solid fa-file-signature"></i>
                        Constructor de Simulacros
                    </h1>
                    <p className="exm-subtitle">
                        Configura y calibra simulacros oficiales ICFES para los estudiantes de ATHENA.
                    </p>
                </div>
                <button
                    className="exm-btn-save-header"
                    type="button"
                    onClick={handleSaveExam}
                >
                    <i className="fa-solid fa-floppy-disk"></i>
                    <span>Guardar simulacro</span>
                </button>
            </header>

            {/* AVISO DE GUARDADO */}
            {savedNotice && (
                <div className="exm-toast-success">
                    <i className="fa-solid fa-circle-check"></i>
                    <span>
                        ¡Simulacro <strong>"{examName}"</strong> guardado exitosamente en el catálogo!
                    </span>
                </div>
            )}

            {/* 2 & 4. LAYOUT PRINCIPAL: FORMULARIO + RESUMEN */}
            <div className="exm-layout-grid">
                {/* COLUMNA IZQUIERDA: CONFIGURACIÓN Y MATERIAS */}
                <div className="exm-form-column">
                    {/* 2. PANEL CONFIGURACIÓN */}
                    <div className="exm-panel-card">
                        <h2 className="exm-panel-title">
                            <i className="fa-solid fa-sliders"></i>
                            Parámetros Generales
                        </h2>

                        {/* Nombre del simulacro */}
                        <div className="exm-form-group">
                            <label className="exm-form-label" htmlFor="exam-name">
                                Nombre del Simulacro
                            </label>
                            <input
                                id="exam-name"
                                type="text"
                                className="exm-form-input"
                                placeholder="Ej: Simulacro Saber 11 - Primera Sesión"
                                value={examName}
                                onChange={(e) => setExamName(e.target.value)}
                                required
                            />
                        </div>

                        {/* Tipo de Simulacro (Pill Buttons) */}
                        <div className="exm-form-group">
                            <label className="exm-form-label">Tipo de Simulacro</label>
                            <div className="exm-type-pills-grid">
                                <div
                                    className={`exm-type-pill ${examType === "Completo" ? "exm-type-pill--active" : ""}`}
                                    onClick={() => handleTypeChange("Completo")}
                                >
                                    <span className="exm-type-pill-title">Completo</span>
                                    <span className="exm-type-pill-desc">Todas las áreas (254 preg)</span>
                                </div>

                                <div
                                    className={`exm-type-pill ${examType === "Mini" ? "exm-type-pill--active" : ""}`}
                                    onClick={() => handleTypeChange("Mini")}
                                >
                                    <span className="exm-type-pill-title">Mini</span>
                                    <span className="exm-type-pill-desc">Prueba rápida (60 preg)</span>
                                </div>

                                <div
                                    className={`exm-type-pill ${examType === "Por áreas" ? "exm-type-pill--active" : ""}`}
                                    onClick={() => handleTypeChange("Por áreas")}
                                >
                                    <span className="exm-type-pill-title">Por áreas</span>
                                    <span className="exm-type-pill-desc">Personalizado modular</span>
                                </div>
                            </div>
                        </div>

                        {/* Dificultad y Tiempo */}
                        <div className="exm-form-row-2">
                            <div className="exm-form-group">
                                <label className="exm-form-label" htmlFor="exam-diff">
                                    Dificultad del Examen
                                </label>
                                <select
                                    id="exam-diff"
                                    className="exm-form-select"
                                    value={difficulty}
                                    onChange={(e) => setDifficulty(e.target.value)}
                                >
                                    <option value="Fácil">Fácil (Básico)</option>
                                    <option value="Media">Media (Estándar ICFES)</option>
                                    <option value="Difícil">Difícil (Avanzado)</option>
                                    <option value="Adaptativa">Adaptativa (IA Dinámica)</option>
                                </select>
                            </div>

                            <div className="exm-form-group">
                                <div className="exm-time-box">
                                    <label className="exm-form-label" htmlFor="exam-time">
                                        Tiempo Límite
                                    </label>
                                    <div className="exm-time-input-wrap">
                                        <input
                                            id="exam-time"
                                            type="number"
                                            min="15"
                                            max="360"
                                            step="5"
                                            className="exm-form-input"
                                            value={durationMinutes}
                                            onChange={(e) => setDurationMinutes(Number(e.target.value))}
                                        />
                                        <span className="exm-time-unit">minutos</span>
                                    </div>
                                    <div className="exm-time-quick-pills">
                                        <button
                                            type="button"
                                            className={`exm-time-quick-btn ${durationMinutes === 60 ? "exm-time-quick-btn--active" : ""}`}
                                            onClick={() => setDurationMinutes(60)}
                                        >
                                            1h
                                        </button>
                                        <button
                                            type="button"
                                            className={`exm-time-quick-btn ${durationMinutes === 120 ? "exm-time-quick-btn--active" : ""}`}
                                            onClick={() => setDurationMinutes(120)}
                                        >
                                            2h
                                        </button>
                                        <button
                                            type="button"
                                            className={`exm-time-quick-btn ${durationMinutes === 270 ? "exm-time-quick-btn--active" : ""}`}
                                            onClick={() => setDurationMinutes(270)}
                                        >
                                            4.5h (Oficial)
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* 3. MATERIAS (CHECKBOXES 5 MATERIAS) */}
                    <div className="exm-panel-card">
                        <h2 className="exm-panel-title">
                            <i className="fa-solid fa-book-bookmark"></i>
                            Materias Incluidas en el Simulacro
                        </h2>

                        <div className="exm-subjects-checklist">
                            {subjects.map((subj) => {
                                const qCount = examType === "Mini" ? subj.questionsMini : subj.questionsFull;

                                return (
                                    <div
                                        key={subj.id}
                                        className={`exm-subject-check-item ${subj.selected ? "exm-subject-check-item--checked" : ""}`}
                                        onClick={() => toggleSubject(subj.id)}
                                    >
                                        <div className="exm-subject-check-left">
                                            <div
                                                className={`exm-custom-checkbox ${subj.selected ? "exm-custom-checkbox--checked" : ""}`}
                                            >
                                                {subj.selected && <i className="fa-solid fa-check"></i>}
                                            </div>
                                            <div
                                                className="exm-subject-icon-box"
                                                style={{ backgroundColor: `${subj.color}18`, color: subj.color }}
                                            >
                                                <i className={subj.icon}></i>
                                            </div>
                                            <span className="exm-subject-name">{subj.name}</span>
                                        </div>

                                        <span className="exm-subject-questions-badge">
                                            {subj.selected ? `${qCount} preguntas` : "Desactivada"}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>

                {/* 4. RESUMEN LATERAL DINÁMICO */}
                <aside className="exm-summary-panel">
                    <div className="exm-summary-header">
                        <h3 className="exm-summary-title">
                            <i className="fa-solid fa-clipboard-list"></i>
                            Resumen del Examen
                        </h3>
                        <span className="exm-summary-tag">Vista previa</span>
                    </div>

                    <div className="exm-summary-list">
                        <div className="exm-summary-item">
                            <span className="exm-summary-label">
                                <i className="fa-solid fa-tag"></i> Tipo:
                            </span>
                            <span className="exm-summary-value">{examType}</span>
                        </div>

                        <div className="exm-summary-item">
                            <span className="exm-summary-label">
                                <i className="fa-solid fa-circle-question"></i> Preguntas totales:
                            </span>
                            <span className="exm-summary-value exm-summary-value--highlight">
                                {totalQuestionsCount}
                            </span>
                        </div>

                        <div className="exm-summary-item">
                            <span className="exm-summary-label">
                                <i className="fa-solid fa-clock"></i> Tiempo asignado:
                            </span>
                            <span className="exm-summary-value">
                                {durationMinutes} min ({Math.floor(durationMinutes / 60)}h {durationMinutes % 60}m)
                            </span>
                        </div>

                        <div className="exm-summary-item">
                            <span className="exm-summary-label">
                                <i className="fa-solid fa-gauge-high"></i> Dificultad:
                            </span>
                            <span className="exm-summary-value">{difficulty}</span>
                        </div>

                        <div className="exm-summary-item">
                            <span className="exm-summary-label">
                                <i className="fa-solid fa-trophy"></i> Puntaje Máximo:
                            </span>
                            <span className="exm-summary-value">500 pts</span>
                        </div>
                    </div>

                    {/* Chips de Materias Seleccionadas */}
                    <div className="exm-summary-subjects-box">
                        <span className="exm-summary-subjects-title">
                            Materias Activas ({selectedSubjects.length} de 5)
                        </span>

                        {selectedSubjects.length > 0 ? (
                            <div className="exm-summary-chips">
                                {selectedSubjects.map((s) => (
                                    <span key={s.id} className="exm-summary-chip">
                                        <span
                                            className="exm-summary-chip-dot"
                                            style={{ backgroundColor: s.color }}
                                        ></span>
                                        {s.name}
                                    </span>
                                ))}
                            </div>
                        ) : (
                            <span style={{ fontSize: "0.78rem", color: "#ef4444" }}>
                                <i className="fa-solid fa-triangle-exclamation"></i> Selecciona al menos una materia.
                            </span>
                        )}
                    </div>

                    <button
                        className="exm-btn-save-bottom"
                        type="button"
                        onClick={handleSaveExam}
                        disabled={selectedSubjects.length === 0}
                    >
                        <i className="fa-solid fa-check"></i>
                        <span>Guardar Simulacro</span>
                    </button>
                </aside>
            </div>
        </div>
    );
};

export default Exams;
