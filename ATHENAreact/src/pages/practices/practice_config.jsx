import "./practice_config.css";
import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { getStudentSubjects } from "../../api/studentQuestions";

// 5 Áreas Oficiales del ICFES con IDs sincronizados con Supabase PostgreSQL
const BASE_ICFES_AREAS = [
    {
        id: 1,
        name: "Lectura Crítica",
        slug: "lectura-critica",
        icon: "fa-book-open",
        className: "reading-area",
        description: "Textos literarios, argumentativos, discontinuos, historietas e infografías.",
        defaultMastery: 75,
        topics: [
            { id: null, name: "Todas las preguntas del área" },
            { id: 1, name: "Identificación de Tesis y Argumentación" },
            { id: 2, name: "Análisis de Textos Discontinuos (Infografías / Caricaturas)" },
            { id: 3, name: "Reflexión Filosófica y Crítica" },
        ],
    },
    {
        id: 2,
        name: "Matemáticas",
        slug: "matematicas",
        icon: "fa-calculator",
        className: "math-area",
        description: "Razonamiento cuantitativo, álgebra, cálculo, estadística y geometría.",
        defaultMastery: 60,
        topics: [
            { id: null, name: "Todas las preguntas del área" },
            { id: 4, name: "Estadística y Probabilidad" },
            { id: 5, name: "Geometría y Trigonometría" },
            { id: 6, name: "Álgebra y Funciones" },
        ],
    },
    {
        id: 3,
        name: "Ciencias Naturales",
        slug: "ciencias-naturales",
        icon: "fa-flask",
        className: "science-area",
        description: "Biología, física, química y componente de Ciencia, Tecnología y Sociedad.",
        defaultMastery: 55,
        topics: [
            { id: null, name: "Todas las preguntas del área" },
            { id: 7, name: "Genética y Ecosistemas" },
            { id: 8, name: "Mecánica Clásica y Termodinámica" },
            { id: 9, name: "Estructura de la Materia y Soluciones" },
        ],
    },
    {
        id: 4,
        name: "Sociales y Ciudadanas",
        slug: "sociales-ciudadanas",
        icon: "fa-landmark",
        className: "social-area",
        description: "Historia de Colombia, constitución política, derechos humanos y pensamiento social.",
        defaultMastery: 70,
        topics: [
            { id: null, name: "Todas las preguntas del área" },
            { id: 10, name: "Constitución y Participación Ciudadana" },
            { id: 11, name: "Historia y Memoria del Conflicto" },
            { id: 12, name: "Economía y Globalización" },
        ],
    },
    {
        id: 5,
        name: "Inglés",
        slug: "ingles",
        icon: "fa-earth-americas",
        className: "english-area",
        description: "Comprensión de lectura, gramática, vocabulario y avisos (niveles A1 a B+).",
        defaultMastery: 50,
        topics: [
            { id: null, name: "Todas las preguntas del área" },
            { id: 13, name: "Tiempos Verbales y Gramática" },
            { id: 14, name: "Vocabulario Contextual" },
            { id: 15, name: "Comprensión Lectora Partes 1 a 7" },
        ],
    },
];

const PracticeConfig = () => {
    const navigate = useNavigate();

    // Catálogo dinámico de materias sincronizado con el backend
    const [publishedCounts, setPublishedCounts] = useState({});
    const [loadingSubjects, setLoadingSubjects] = useState(true);

    // Modal de configuración de la práctica por área seleccionada
    const [selectedArea, setSelectedArea] = useState(null);
    const [selectedTopicId, setSelectedTopicId] = useState(null);
    const [selectedDifficulty, setSelectedDifficulty] = useState("all");
    const [selectedQuestionLimit, setSelectedQuestionLimit] = useState(49);

    const [loading, setLoading] = useState(false);
    const [error, setError] = useState("");

    // Cargar conteos reales de preguntas publicadas desde el microservicio
    useEffect(() => {
        const fetchSubjects = async () => {
            try {
                setLoadingSubjects(true);
                const res = await getStudentSubjects();
                if (res && res.success && Array.isArray(res.data)) {
                    const countsMap = {};
                    res.data.forEach((s) => {
                        countsMap[s.id] = s.published_count || 0;
                    });
                    setPublishedCounts(countsMap);
                }
            } catch (err) {
                console.error("Error al cargar catálogo de materias para estudiante:", err);
            } finally {
                setLoadingSubjects(false);
            }
        };

        fetchSubjects();
    }, []);

    const handleOpenArea = (area) => {
        setSelectedArea(area);
        setSelectedTopicId(null);
        setSelectedDifficulty("all");
        setError("");
        const available = publishedCounts[area.id] || 0;
        setSelectedQuestionLimit(available > 0 ? (available >= 25 ? 25 : available) : 10);
    };

    const handleCloseModal = () => {
        if (!loading) {
            setSelectedArea(null);
            setError("");
        }
    };

    const handleStartPractice = () => {
        if (!selectedArea) return;

        const available = publishedCounts[selectedArea.id] || 0;
        if (available === 0) {
            setError(
                `Actualmente no hay preguntas publicadas en ${selectedArea.name}. El equipo académico está cargando los cuadernillos oficiales.`
            );
            return;
        }

        setLoading(true);

        // Navegar a la sala de práctica con los parámetros reales del banco
        const params = new URLSearchParams({
            subject_id: selectedArea.id,
            subject_name: selectedArea.name,
            limit: selectedQuestionLimit || available,
            difficulty: selectedDifficulty,
        });

        if (selectedTopicId !== null && selectedTopicId !== undefined) {
            params.append("topic_id", selectedTopicId);
        }

        navigate(`/practices-full?${params.toString()}`);
    };

    return (
        <div className="practice-container">
            {/* HEADER */}
            <header className="page-header">
                <div className="header-text">
                    <h1>Prácticas ICFES Saber 11</h1>
                    <p>
                        Entrena con preguntas reales extraídas de cuadernillos oficiales, incluyendo textos completos, infografías, caricaturas e historietas con retroalimentación inmediata.
                    </p>
                </div>

                {/* OBJETIVO DE SESIÓN */}
                <div className="daily-goal-card">
                    <div className="goal-icon">
                        <i className="fas fa-bullseye"></i>
                    </div>
                    <div className="goal-info">
                        <p>
                            Banco Oficial Activo:{" "}
                            <span className="goal-count">
                                {Object.values(publishedCounts).reduce((a, b) => a + b, 0)}
                            </span>{" "}
                            Preguntas
                        </p>
                        <div className="goal-progress-bg">
                            <div className="goal-progress-fill" style={{ width: "100%" }}></div>
                        </div>
                    </div>
                </div>
            </header>

            {/* AVISO METODOLÓGICO */}
            <div className="practice-notice-banner">
                <i className="fas fa-layer-group" style={{ color: "#38bdf8", fontSize: "18px" }}></i>
                <div>
                    <strong>Entrenamiento con Preguntas Reales:</strong> Los reactivos provienen directamente de cuadernillos oficiales ICFES Saber 11 procesados mediante extracción multimodal. Resuelve a tu ritmo y consulta la clave oficial inmediatamente después de cada respuesta.
                </div>
            </div>

            {/* GRID DE LAS 5 ÁREAS OFICIALES */}
            <div className="areas-grid">
                {BASE_ICFES_AREAS.map((area) => {
                    const count = publishedCounts[area.id] || 0;
                    const hasQuestions = count > 0;

                    return (
                        <div
                            className={`area-card ${area.className}`}
                            key={area.id}
                            onClick={() => handleOpenArea(area)}
                            style={{
                                cursor: "pointer",
                                opacity: hasQuestions ? 1 : 0.82,
                                border: hasQuestions ? "1px solid rgba(56, 189, 248, 0.4)" : undefined,
                            }}
                        >
                            <div className="card-top">
                                <div className="area-icon">
                                    <i className={`fas ${area.icon}`}></i>
                                </div>
                                {hasQuestions ? (
                                    <span
                                        style={{
                                            background: "rgba(34, 197, 94, 0.15)",
                                            color: "#4ade80",
                                            border: "1px solid rgba(34, 197, 94, 0.35)",
                                            padding: "3px 8px",
                                            borderRadius: "6px",
                                            fontSize: "0.72rem",
                                            fontWeight: 700,
                                        }}
                                    >
                                        <i className="fas fa-check-circle" style={{ marginRight: 4 }}></i> {count} Disponibles
                                    </span>
                                ) : (
                                    <span
                                        style={{
                                            background: "rgba(148, 163, 184, 0.1)",
                                            color: "#94a3b8",
                                            padding: "3px 8px",
                                            borderRadius: "6px",
                                            fontSize: "0.72rem",
                                        }}
                                    >
                                        Próximamente
                                    </span>
                                )}
                            </div>

                            <h3>{area.name}</h3>
                            <p className="description">{area.description}</p>

                            <div className="mastery-section">
                                <div className="mastery-label">
                                    <span>Preguntas en Banco</span>
                                    <span className="percentage" style={{ color: hasQuestions ? "#38bdf8" : "#94a3b8" }}>
                                        {loadingSubjects ? "Cargando..." : `${count} Reactivos`}
                                    </span>
                                </div>

                                <div className="mastery-bar">
                                    <div
                                        className="fill"
                                        style={{
                                            width: hasQuestions ? "100%" : "0%",
                                            background: hasQuestions ? "linear-gradient(90deg, #0284c7, #38bdf8)" : "#334155",
                                        }}
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
                                style={{
                                    background: hasQuestions ? undefined : "rgba(148, 163, 184, 0.15)",
                                    color: hasQuestions ? undefined : "#94a3b8",
                                }}
                            >
                                {hasQuestions ? "Iniciar Entrenamiento" : "Consultar Área"}
                                <i className="fas fa-play" style={{ marginLeft: "6px" }}></i>
                            </button>
                        </div>
                    );
                })}
            </div>

            {/* MODAL / PANEL DE CONFIGURACIÓN DE LA PRÁCTICA */}
            {selectedArea && (
                <div className="practice-modal-overlay" onClick={handleCloseModal}>
                    <div className="practice-modal" onClick={(e) => e.stopPropagation()}>
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
                            {/* CANTIDAD DE PREGUNTAS A PRACTICAR */}
                            <div className="practice-config-row">
                                <label>Cantidad de Preguntas a Resolver</label>
                                <div className="practice-format-box">
                                    <div style={{ display: "flex", gap: "10px", flexWrap: "wrap", marginTop: "6px" }}>
                                        {[10, 20, 25, publishedCounts[selectedArea.id] || 49]
                                            .filter((num, i, arr) => num > 0 && arr.indexOf(num) === i)
                                            .map((num) => (
                                                <button
                                                    key={num}
                                                    type="button"
                                                    onClick={() => setSelectedQuestionLimit(num)}
                                                    style={{
                                                        padding: "8px 16px",
                                                        borderRadius: "8px",
                                                        border: selectedQuestionLimit === num ? "2px solid #38bdf8" : "1px solid rgba(148, 163, 184, 0.3)",
                                                        background: selectedQuestionLimit === num ? "rgba(56, 189, 248, 0.15)" : "rgba(15, 23, 42, 0.6)",
                                                        color: selectedQuestionLimit === num ? "#38bdf8" : "#f1f5f9",
                                                        fontWeight: 700,
                                                        cursor: "pointer",
                                                        fontSize: "13px",
                                                    }}
                                                >
                                                    {num === (publishedCounts[selectedArea.id] || 49) ? `Todas (${num})` : `${num} Preguntas`}
                                                </button>
                                            ))}
                                    </div>
                                    <p className="format-hint" style={{ marginTop: "8px" }}>
                                        Disponibles en el banco: <strong>{publishedCounts[selectedArea.id] || 0} preguntas</strong> publicadas.
                                    </p>
                                </div>
                            </div>

                            {/* SELECTOR DE DIFICULTAD */}
                            <div className="practice-config-row">
                                <label htmlFor="diff-select">Filtro de Dificultad</label>
                                <select
                                    id="diff-select"
                                    className="practice-select"
                                    value={selectedDifficulty}
                                    onChange={(e) => setSelectedDifficulty(e.target.value)}
                                >
                                    <option value="all">Todas las dificultades (Recomendado para práctica integral)</option>
                                    <option value="easy">Fácil</option>
                                    <option value="medium">Media</option>
                                    <option value="hard">Difícil</option>
                                </select>
                            </div>

                            {/* NOTA SOBRE ESTÍMULOS Y CLÚSTERES */}
                            <div className="practice-info-box">
                                <i className="fas fa-circle-info" style={{ color: "#60a5fa" }}></i>
                                <span>
                                    <strong>Estímulos e imágenes:</strong> Cada pregunta incluye su lectura o recurso gráfico (caricaturas de Quino, Calvin & Hobbes, infografía OMS) y retroalimentación pedagógica al instante de responder.
                                </span>
                            </div>

                            {error && (
                                <div
                                    className="practice-error-message"
                                    style={{
                                        display: "flex",
                                        gap: "10px",
                                        alignItems: "flex-start",
                                        background: "rgba(239, 68, 68, 0.12)",
                                        border: "1px solid rgba(239, 68, 68, 0.4)",
                                        borderRadius: "8px",
                                        padding: "12px 16px",
                                        color: "#fca5a5",
                                        fontSize: "14px",
                                        marginTop: "14px",
                                    }}
                                >
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
                                disabled={loading || (publishedCounts[selectedArea.id] || 0) === 0}
                                style={{
                                    opacity: (publishedCounts[selectedArea.id] || 0) === 0 ? 0.5 : 1,
                                    cursor: (publishedCounts[selectedArea.id] || 0) === 0 ? "not-allowed" : "pointer",
                                }}
                            >
                                {loading ? (
                                    <>
                                        <i className="fas fa-spinner fa-spin"></i> Cargando banco...
                                    </>
                                ) : (
                                    <>
                                        <i className="fas fa-play"></i> Iniciar Práctica ({selectedQuestionLimit} Preguntas)
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
