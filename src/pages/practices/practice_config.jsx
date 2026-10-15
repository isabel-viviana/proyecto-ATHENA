import "./practice_config.css";
import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { configureExam } from "../../api/exams";

// 5 Áreas Oficiales del ICFES con IDs y Temas sincronizados exactamente con MySQL
const ICFES_AREAS = [
    {
        id: 1,
        name: "Matemáticas",
        slug: "matematicas",
        icon: "fa-calculator",
        className: "math-area",
        description: "Razonamiento cuantitativo, álgebra, cálculo y geometría.",
        defaultMastery: 65,
        topics: [
            { id: null, name: "Todos los temas del área" },
            { id: 1, name: "Estadística y Probabilidad" },
            { id: 2, name: "Trigonometría Básica" },
            { id: 3, name: "Ecuaciones Lineales" },
        ],
    },
    {
        id: 2,
        name: "Ciencias Naturales",
        slug: "ciencias-naturales",
        icon: "fa-flask",
        className: "science-area",
        description: "Biología, física, química y componente CTS.",
        defaultMastery: 55,
        topics: [
            { id: null, name: "Todos los temas del área" },
            { id: 4, name: "Genética y Célula" },
            { id: 5, name: "Cinemática (Física)" },
            { id: 6, name: "Estequiometría (Química)" },
        ],
    },
    {
        id: 3,
        name: "Lectura Crítica",
        slug: "lectura-critica",
        icon: "fa-book-open",
        className: "reading-area",
        description: "Textos literarios, argumentativos y textos discontinuos.",
        defaultMastery: 40,
        topics: [
            { id: null, name: "Todos los temas del área" },
            { id: 7, name: "Identificación de Tesis" },
            { id: 8, name: "Figuras Literarias" },
            { id: 9, name: "Análisis de Infografías" },
        ],
    },
    {
        id: 4,
        name: "Sociales y Ciudadanas",
        slug: "sociales-ciudadanas",
        icon: "fa-landmark",
        className: "social-area",
        description: "Historia, geografía, constitución y análisis de perspectivas.",
        defaultMastery: 80,
        topics: [
            { id: null, name: "Todos los temas del área" },
            { id: 10, name: "Mecanismos de Participación" },
            { id: 11, name: "La Patria Boba" },
            { id: 12, name: "Conflictos Globales (Siglo XX)" },
        ],
    },
    {
        id: 5,
        name: "Inglés",
        slug: "ingles",
        icon: "fa-earth-americas",
        className: "english-area",
        description: "Vocabulario, gramática y comprensión lectora (A1 a B1+).",
        defaultMastery: 30,
        topics: [
            { id: null, name: "Todos los temas del área" },
            { id: 13, name: "Tiempos Verbales (Past/Present)" },
            { id: 14, name: "Phrasal Verbs" },
            { id: 15, name: "Reading Comprehension Parts 1-7" },
        ],
    },
];

const PracticeConfig = () => {
    const navigate = useNavigate();

    // Modal de configuración de la práctica por área seleccionada
    const [selectedArea, setSelectedArea] = useState(null);
    const [selectedTopicId, setSelectedTopicId] = useState(null);
    const [selectedDifficulty, setSelectedDifficulty] = useState("medium");

    const [loading, setLoading] = useState(false);
    const [error, setError] = useState("");
    const [notice, setNotice] = useState("");

    const handleOpenArea = (area) => {
        setSelectedArea(area);
        setSelectedTopicId(null);
        setSelectedDifficulty("medium");
        setError("");
        setNotice("");
    };

    const handleCloseModal = () => {
        if (!loading) {
            setSelectedArea(null);
            setError("");
            setNotice("");
        }
    };

    const handleStartPractice = async () => {
        if (!selectedArea) return;

        setLoading(true);
        setError("");
        setNotice("");

        try {
            /*
             * Configuración de práctica por área:
             * - exam_type: 'practice' (meta: 25 preguntas)
             * - difficulty: dificultad seleccionada
             * - subject_ids: ID único del área ICFES seleccionada
             * - topic_id: ID opcional de tema si se seleccionó uno específico
             */
            const payload = {
                exam_type: "practice",
                difficulty: selectedDifficulty,
                subject_ids: [selectedArea.id],
            };

            if (selectedTopicId !== null && selectedTopicId !== undefined) {
                payload.topic_id = Number(selectedTopicId);
            }

            const response = await configureExam(payload);

            const responseData = response?.data ?? response;
            const practiceId =
                responseData?.data?.attempt?.id ||
                responseData?.attempt?.id ||
                responseData?.data?.id ||
                responseData?.attempt_id ||
                responseData?.id;

            if (!practiceId) {
                throw new Error("No se obtuvo el identificador de la práctica desde el servidor.");
            }

            // Si el backend aplicó dificultad mixta de respaldo
            const metadata = responseData?.data?.metadata || responseData?.metadata;
            const noticeMsg = metadata?.difficulty_fallback ? metadata?.difficulty_reason : null;

            // Navega al ejecutor real de práctica con el ID del intento
            navigate(`/practices-full?id=${practiceId}`, {
                state: { notice: noticeMsg },
            });
        } catch (err) {
            console.error("Error al iniciar práctica:", err);

            if (err?.response?.status === 422) {
                const serverMsg = err.response?.data?.message;
                const errors = err.response?.data?.errors;
                const available = errors?.available;
                const required = errors?.required || 25;

                setError(
                    serverMsg ||
                        `El banco cuenta con ${available ?? 0} preguntas publicadas para esta selección. Se requieren ${required} preguntas para iniciar una práctica completa.`
                );
            } else {
                setError(
                    err?.response?.data?.message ||
                        err?.message ||
                        "No se pudo iniciar la práctica. Verifica tu conexión con el servidor."
                );
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="practice-container">
            {/* HEADER */}
            <header className="page-header">
                <div className="header-text">
                    <h1>Prácticas por Área</h1>
                    <p>
                        Entrena de forma focalizada en cada una de las 5 competencias oficiales del examen Saber 11.
                    </p>
                </div>

                {/* OBJETIVO DE SESIÓN */}
                <div className="daily-goal-card">
                    <div className="goal-icon">
                        <i className="fas fa-bullseye"></i>
                    </div>
                    <div className="goal-info">
                        <p>
                            Objetivo de Sesión:{" "}
                            <span className="goal-count">25</span> Preguntas
                        </p>
                        <div className="goal-progress-bg">
                            <div
                                className="goal-progress-fill"
                                style={{ width: "100%" }}
                            ></div>
                        </div>
                    </div>
                </div>
            </header>

            {/* AVISO METODOLÓGICO */}
            <div className="practice-notice-banner">
                <i className="fas fa-layer-group" style={{ color: "#38bdf8", fontSize: "18px" }}></i>
                <div>
                    <strong>Entrenamiento Focalizado por Área:</strong> Selecciona un área para comenzar una práctica intensiva de 25 preguntas. Las preguntas proceden directamente del banco académico verificado de ATHENA.
                </div>
            </div>

            {/* GRID DE LAS 5 ÁREAS OFICIALES */}
            <div className="areas-grid">
                {ICFES_AREAS.map((area) => (
                    <div
                        className={`area-card ${area.className}`}
                        key={area.id}
                        onClick={() => handleOpenArea(area)}
                        style={{ cursor: "pointer" }}
                    >
                        <div className="card-top">
                            <div className="area-icon">
                                <i className={`fas ${area.icon}`}></i>
                            </div>
                            <i className="fas fa-chevron-right arrow-detail"></i>
                        </div>

                        <h3>{area.name}</h3>
                        <p className="description">{area.description}</p>

                        <div className="mastery-section">
                            <div className="mastery-label">
                                <span>Dominio Estimado</span>
                                <span className="percentage">{area.defaultMastery}%</span>
                            </div>

                            <div className="mastery-bar">
                                <div
                                    className="fill"
                                    style={{ width: `${area.defaultMastery}%` }}
                                ></div>
                            </div>
                        </div>

                        <button
                            type="button"
                            className="practice-btn"
                            onClick={(e) => {
                                e.stopPropagation();
                                handleOpenArea(area);
                            }}
                        >
                            Configurar Práctica <i className="fas fa-play" style={{ marginLeft: "6px" }}></i>
                        </button>
                    </div>
                ))}
            </div>

            {/* MODAL / PANEL DE CONFIGURACIÓN DE LA PRÁCTICA */}
            {selectedArea && (
                <div className="practice-modal-overlay" onClick={handleCloseModal}>
                    <div
                        className="practice-modal"
                        onClick={(e) => e.stopPropagation()}
                    >
                        {/* MODAL HEADER */}
                        <div className="practice-modal__header">
                            <div className="practice-modal__title-box">
                                <div className="area-icon" style={{ width: "42px", height: "42px", fontSize: "18px" }}>
                                    <i className={`fas ${selectedArea.icon}`}></i>
                                </div>
                                <div>
                                    <h2>Práctica: {selectedArea.name}</h2>
                                    <p>{selectedArea.description}</p>
                                </div>
                            </div>
                            <button
                                type="button"
                                className="practice-modal__close"
                                onClick={handleCloseModal}
                                disabled={loading}
                            >
                                <i className="fas fa-xmark"></i>
                            </button>
                        </div>

                        {/* MODAL BODY */}
                        <div className="practice-modal__body">
                            {/* ESTÁNDAR DE 25 PREGUNTAS */}
                            <div className="practice-config-row">
                                <label>Formato de la Sesión</label>
                                <div className="practice-format-box">
                                    <div className="format-stat">
                                        <strong>25 Preguntas</strong>
                                        <span className="badge-standard">Estándar ICFES</span>
                                    </div>
                                    <p className="format-hint">
                                        Sesión de práctica oficial de 25 preguntas respetando textos e infografías en bloque.
                                    </p>
                                </div>
                            </div>

                            {/* SELECTOR DE TEMA CON ID REAL */}
                            <div className="practice-config-row">
                                <label htmlFor="topic-select">
                                    Eje Temático <span className="label-badge">Opcional</span>
                                </label>
                                <select
                                    id="topic-select"
                                    className="practice-select"
                                    value={selectedTopicId ?? ""}
                                    onChange={(e) => {
                                        const val = e.target.value;
                                        setSelectedTopicId(val === "" ? null : Number(val));
                                    }}
                                >
                                    {selectedArea.topics.map((t, idx) => (
                                        <option key={idx} value={t.id ?? ""}>
                                            {t.name}
                                        </option>
                                    ))}
                                </select>
                                <small className="field-hint">
                                    Puedes practicar toda el área o focalizarte en un eje temático específico.
                                </small>
                            </div>

                            {/* SELECTOR DE DIFICULTAD */}
                            <div className="practice-config-row">
                                <label htmlFor="diff-select">
                                    Nivel de Dificultad
                                </label>
                                <select
                                    id="diff-select"
                                    className="practice-select"
                                    value={selectedDifficulty}
                                    onChange={(e) => setSelectedDifficulty(e.target.value)}
                                >
                                    <option value="medium">Media (Recomendada para práctica estándar)</option>
                                    <option value="easy">Fácil (Refuerzo de conceptos básicos)</option>
                                    <option value="hard">Difícil (Desafío avanzado)</option>
                                </select>
                            </div>

                            {/* NOTA SOBRE ESTÍMULOS Y CLÚSTERES */}
                            <div className="practice-info-box">
                                <i className="fas fa-circle-info" style={{ color: "#60a5fa" }}></i>
                                <span>
                                    <strong>Estímulos indivisibles:</strong> Las lecturas o gráficas compartidas se presentarán completas y en secuencia pedagógica.
                                </span>
                            </div>

                            {notice && (
                                <div style={{ background: "rgba(56, 189, 248, 0.1)", border: "1px solid rgba(56, 189, 248, 0.3)", borderRadius: "8px", padding: "12px", color: "#38bdf8", fontSize: "13px", marginTop: "12px" }}>
                                    <i className="fas fa-info-circle" style={{ marginRight: "6px" }}></i>
                                    {notice}
                                </div>
                            )}

                            {error && (
                                <div className="practice-error-message" style={{ display: "flex", gap: "10px", alignItems: "flex-start", background: "rgba(239, 68, 68, 0.12)", border: "1px solid rgba(239, 68, 68, 0.4)", borderRadius: "8px", padding: "12px 16px", color: "#fca5a5", fontSize: "14px", marginTop: "14px" }}>
                                    <i className="fas fa-triangle-exclamation" style={{ marginTop: "3px", color: "#ef4444" }}></i>
                                    <span>{error}</span>
                                </div>
                            )}
                        </div>

                        {/* MODAL ACTIONS */}
                        <div className="practice-modal__actions">
                            <button
                                type="button"
                                className="btn-cancel"
                                onClick={handleCloseModal}
                                disabled={loading}
                            >
                                Cancelar
                            </button>

                            <button
                                type="button"
                                className="btn-start"
                                onClick={handleStartPractice}
                                disabled={loading}
                            >
                                {loading ? (
                                    <>
                                        <i className="fas fa-spinner fa-spin"></i> Preparando sesión...
                                    </>
                                ) : (
                                    <>
                                        <i className="fas fa-play"></i> Iniciar Práctica (25 Preguntas)
                                    </>
                                )}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default PracticeConfig;
