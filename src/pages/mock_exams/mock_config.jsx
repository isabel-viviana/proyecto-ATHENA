import "./mock_config.css";
import { useState } from "react";
import { useNavigate, Link } from "react-router-dom";
import Zorrito from "../../assets/images/Zorrito2D.png";
import { configureExam } from "../../api/exams";

// IDs sincronizados exactamente con MySQL (DatabaseSeeder.php)
const INITIAL_SUBJECTS = [
    { id: 1, name: "Matemáticas", icon: "fa-calculator", selected: true },
    { id: 2, name: "Ciencias Naturales", icon: "fa-flask", selected: true },
    { id: 3, name: "Lectura Crítica", icon: "fa-book-open", selected: true },
    { id: 4, name: "Sociales y Ciudadanas", icon: "fa-landmark", selected: true },
    { id: 5, name: "Inglés", icon: "fa-earth-americas", selected: true },
];

const MockConfig = () => {
    const navigate = useNavigate();

    // Modalidades permitidas: 'full' (Simulacro Completo ICFES) y 'quick' (Mini Simulacro)
    const [examType, setExamType] = useState("full");
    const [difficulty, setDifficulty] = useState("medium");
    const [subjects, setSubjects] = useState(INITIAL_SUBJECTS);

    const [loading, setLoading] = useState(false);
    const [error, setError] = useState("");

    const toggleSubject = (id) => {
        // En simulacro completo no se permite alterar las áreas del cuadernillo oficial
        if (examType === "full") return;

        setSubjects((current) =>
            current.map((subject) =>
                subject.id === id
                    ? {
                          ...subject,
                          selected: !subject.selected,
                      }
                    : subject
            )
        );
    };

    const handleStartExam = async () => {
        setError("");

        // En simulacro completo siempre se evalúan las 5 áreas oficiales del cuadernillo
        const selectedSubjects =
            examType === "full"
                ? [1, 2, 3, 4, 5]
                : subjects
                      .filter((subject) => subject.selected)
                      .map((subject) => subject.id);

        if (selectedSubjects.length === 0) {
            setError("Debes seleccionar al menos un área para el mini simulacro.");
            return;
        }

        setLoading(true);

        try {
            const response = await configureExam({
                exam_type: examType,
                difficulty: difficulty,
                subject_ids: selectedSubjects,
            });

            console.log("RESPUESTA CONFIGURACIÓN SIMULACRO:", response);

            const responseData = response?.data ?? response;
            const examId =
                responseData?.data?.attempt?.id ||
                responseData?.attempt?.id ||
                responseData?.data?.id ||
                responseData?.attempt_id ||
                responseData?.id;

            if (!examId) {
                throw new Error(
                    "El servidor configuró el simulacro pero no se obtuvo el ID del intento."
                );
            }

            navigate(`/mock/exam?id=${examId}`);
        } catch (err) {
            console.error("ERROR AL CREAR SIMULACRO:", err);
            setError(
                err?.response?.data?.message ||
                    err?.message ||
                    "No se pudo iniciar el simulacro."
            );
        } finally {
            setLoading(false);
        }
    };

    return (
        <section className="mock-config">
            <div className="main__content">
                {/* HEADER */}
                <section className="page-header">
                    <div className="page-header__info">
                        <h1>Configurar Simulacro</h1>
                        <p>
                            Selecciona una modalidad oficial para evaluar tus competencias en las pruebas Saber 11.
                        </p>
                    </div>
                </section>

                {/* CONFIG GRID */}
                <section className="config-grid">
                    {/* LEFT: MODALIDADES PERMITIDAS */}
                    <div className="config-card">
                        <div className="card-title">
                            <i className="fas fa-gear"></i>
                            <h3>Modalidad de Simulacro</h3>
                        </div>

                        {/* 1. SIMULACRO COMPLETO ICFES */}
                        <label
                            className={`option ${
                                examType === "full" ? "option--active" : ""
                            }`}
                        >
                            <div className="option__top">
                                <div className="option__left">
                                    <input
                                        type="radio"
                                        name="examType"
                                        checked={examType === "full"}
                                        onChange={() => setExamType("full")}
                                    />
                                    <div>
                                        <h4>Simulacro Completo ICFES</h4>
                                        <p>
                                            5 áreas oficiales · 2 sesiones reglamentarias · 4h 30m
                                        </p>
                                    </div>
                                </div>
                                <span className="recommended">Oficial ICFES</span>
                            </div>
                        </label>

                        {/* 2. MINI SIMULACRO DIAGNÓSTICO */}
                        <label
                            className={`option ${
                                examType === "quick" ? "option--active" : ""
                            }`}
                        >
                            <div className="option__top">
                                <div className="option__left">
                                    <input
                                        type="radio"
                                        name="examType"
                                        checked={examType === "quick"}
                                        onChange={() => setExamType("quick")}
                                    />
                                    <div>
                                        <h4>Mini Simulacro Diagnóstico</h4>
                                        <p>
                                            Evaluación corta multiárea para diagnóstico ágil
                                        </p>
                                    </div>
                                </div>
                                <span style={{ fontSize: "11px", color: "#60a5fa", fontWeight: 600 }}>
                                    Diagnóstico
                                </span>
                            </div>
                        </label>

                        {/* ENLACE INFORMATIVO HACIA PRÁCTICAS */}
                        <div className="config-redirect-banner">
                            <i className="fas fa-info-circle" style={{ color: "#60a5fa" }}></i>
                            <span>
                                ¿Quieres entrenar una sola materia por separado? Utiliza el módulo de{" "}
                                <Link to="/practices">Prácticas por Área</Link> para sesiones de 25 preguntas.
                            </span>
                        </div>

                        {/* EXTRA SETTINGS */}
                        <div className="extra-settings">
                            {/* DIFICULTAD */}
                            <div className="extra-item">
                                <span>Dificultad</span>
                                <select
                                    value={difficulty}
                                    onChange={(e) => setDifficulty(e.target.value)}
                                >
                                    <option value="medium">Media (Estándar)</option>
                                    <option value="easy">Fácil</option>
                                    <option value="hard">Difícil</option>
                                </select>
                            </div>

                            {/* DURACIÓN ESTIMADA SEGÚN MODALIDAD */}
                            <div className="extra-item">
                                <span>Duración estipulada</span>
                                <strong style={{ color: "#f1f5f9", fontSize: "14px" }}>
                                    {examType === "full" ? "4 horas 30 min" : "45 minutos"}
                                </strong>
                            </div>
                        </div>
                    </div>

                    {/* RIGHT: ÁREAS A EVALUAR */}
                    <div className="config-card">
                        <div className="card-title">
                            <i className="fas fa-file-lines"></i>
                            <h3>Áreas a evaluar</h3>
                        </div>

                        {examType === "full" ? (
                            <div className="mode-lock-notice">
                                <i className="fas fa-lock"></i>
                                <span>
                                    <strong>Cuadernillo Fijo:</strong> Las 5 áreas están fijadas reglamentariamente para el simulacro completo ICFES.
                                </span>
                            </div>
                        ) : (
                            <div className="mode-lock-notice" style={{ borderColor: "#3b82f6" }}>
                                <i className="fas fa-sliders" style={{ color: "#60a5fa" }}></i>
                                <span>
                                    Selecciona las áreas que deseas incluir en este mini simulacro.
                                </span>
                            </div>
                        )}

                        {/* SUBJECTS */}
                        <div className="subjects">
                            {subjects.map((subject) => {
                                const isLocked = examType === "full";
                                const isChecked = isLocked ? true : subject.selected;

                                return (
                                    <label
                                        className={`subject ${isLocked ? "subject--locked" : ""}`}
                                        key={subject.id}
                                    >
                                        <input
                                            type="checkbox"
                                            checked={isChecked}
                                            disabled={isLocked}
                                            onChange={() => toggleSubject(subject.id)}
                                        />
                                        <i className={`fas ${subject.icon}`} style={{ width: "16px", color: "#60a5fa" }}></i>
                                        <span style={{ fontWeight: 500 }}>{subject.name}</span>
                                        {isLocked && (
                                            <span style={{ marginLeft: "auto", fontSize: "11px", color: "#64748b" }}>
                                                Fija
                                            </span>
                                        )}
                                    </label>
                                );
                            })}
                        </div>

                        {/* SUMMARY */}
                        <div className="summary-box">
                            <div className="summary-item">
                                <span>Áreas seleccionadas</span>
                                <strong>
                                    {examType === "full"
                                        ? "5 de 5 (Oficial)"
                                        : `${subjects.filter((s) => s.selected).length} de 5`}
                                </strong>
                            </div>

                            <div className="summary-item">
                                <span>Estructura</span>
                                <strong>
                                    {examType === "full"
                                        ? "2 Sesiones oficiales"
                                        : "Sesión corta"}
                                </strong>
                            </div>

                            <div className="summary-item">
                                <span>Dificultad</span>
                                <strong>
                                    {difficulty === "easy"
                                        ? "Fácil"
                                        : difficulty === "hard"
                                        ? "Difícil"
                                        : "Media"}
                                </strong>
                            </div>
                        </div>
                    </div>
                </section>

                {/* ERROR */}
                {error && <div className="error-message">{error}</div>}

                {/* ACTIONS */}
                <section className="actions">
                    <button
                        className="btn btn--secondary"
                        onClick={() => navigate("/dashboard")}
                        disabled={loading}
                    >
                        Cancelar
                    </button>

                    <button
                        className="btn btn--primary"
                        onClick={handleStartExam}
                        disabled={loading}
                    >
                        {loading ? "Preparando simulacro..." : "Iniciar Prueba"}
                        {!loading && <i className="fas fa-play"></i>}
                    </button>
                </section>
            </div>

            {/* ASSISTANT */}
            <div className="assistant-box">
                <div className="assistant-message">
                    {examType === "full"
                        ? "El simulacro completo evalúa todas las áreas como en la prueba real. ¡Prepárate con tiempo y concentración!"
                        : "El mini simulacro es ideal para medir tu agilidad y diagnosticar tus áreas fuertes."}
                </div>
                <div className="assistant-avatar">
                    <img src={Zorrito} alt="Zorrito" />
                </div>
            </div>
        </section>
    );
};

export default MockConfig;