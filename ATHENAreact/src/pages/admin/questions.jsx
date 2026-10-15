import { useState, useMemo } from "react";
import "./questions.css";

const initialQuestions = [
    {
        id: "MAT-101",
        subject: "Matemáticas",
        topic: "Funciones Cuadráticas y Parábolas",
        difficulty: "Media",
        status: "Activa",
        date: "24 Ago 2026",
        color: "#10b981",
    },
    {
        id: "LEC-204",
        subject: "Lectura Crítica",
        topic: "Identificación de Tesis e Intencionalidad",
        difficulty: "Difícil",
        status: "Activa",
        date: "22 Ago 2026",
        color: "#3b82f6",
    },
    {
        id: "NAT-315",
        subject: "Ciencias Naturales",
        topic: "Termodinámica y Reacciones Químicas",
        difficulty: "Media",
        status: "Revisión",
        date: "20 Ago 2026",
        color: "#a855f7",
    },
    {
        id: "SOC-408",
        subject: "Sociales y Ciudadanas",
        topic: "Mecanismos de Participación Ciudadana",
        difficulty: "Fácil",
        status: "Activa",
        date: "19 Ago 2026",
        color: "#f59e0b",
    },
    {
        id: "ING-512",
        subject: "Inglés",
        topic: "Reading Comprehension - Literal Text B1",
        difficulty: "Fácil",
        status: "Activa",
        date: "18 Ago 2026",
        color: "#06b6d4",
    },
    {
        id: "MAT-102",
        subject: "Matemáticas",
        topic: "Geometría Analítica y Teorema de Pitágoras",
        difficulty: "Fácil",
        status: "Activa",
        date: "16 Ago 2026",
        color: "#10b981",
    },
    {
        id: "LEC-205",
        subject: "Lectura Crítica",
        topic: "Falacias Argumentativas en Medios",
        difficulty: "Media",
        status: "Activa",
        date: "15 Ago 2026",
        color: "#3b82f6",
    },
    {
        id: "NAT-316",
        subject: "Ciencias Naturales",
        topic: "Genética Mendeliana y Árboles Genealógicos",
        difficulty: "Difícil",
        status: "Activa",
        date: "14 Ago 2026",
        color: "#a855f7",
    },
    {
        id: "SOC-409",
        subject: "Sociales y Ciudadanas",
        topic: "Conflicto Armado y Acuerdos de Paz",
        difficulty: "Media",
        status: "Revisión",
        date: "12 Ago 2026",
        color: "#f59e0b",
    },
    {
        id: "ING-513",
        subject: "Inglés",
        topic: "Complete Conversations and Everyday Grammar",
        difficulty: "Media",
        status: "Activa",
        date: "10 Ago 2026",
        color: "#06b6d4",
    },
    {
        id: "MAT-103",
        subject: "Matemáticas",
        topic: "Probabilidad Condicional y Combinatoria",
        difficulty: "Difícil",
        status: "Revisión",
        date: "08 Ago 2026",
        color: "#10b981",
    },
    {
        id: "NAT-317",
        subject: "Ciencias Naturales",
        topic: "Cinemática y Leyes de Newton",
        difficulty: "Fácil",
        status: "Activa",
        date: "05 Ago 2026",
        color: "#a855f7",
    },
];

const Questions = () => {
    const [searchTerm, setSearchTerm] = useState("");
    const [subjectFilter, setSubjectFilter] = useState("Todas");
    const [difficultyFilter, setDifficultyFilter] = useState("Todas");

    // Estado del Modal visual
    const [isModalOpen, setIsModalOpen] = useState(false);

    // Estado del formulario en el Modal
    const [formData, setFormData] = useState({
        subject: "Matemáticas",
        topic: "",
        difficulty: "Media",
        statement: "",
        optionA: "",
        optionB: "",
        optionC: "",
        optionD: "",
        correctOption: "A",
        explanation: "",
    });

    // Filtrado interactivo
    const filteredQuestions = useMemo(() => {
        return initialQuestions.filter((q) => {
            const matchesSearch =
                q.id.toLowerCase().includes(searchTerm.toLowerCase()) ||
                q.subject.toLowerCase().includes(searchTerm.toLowerCase()) ||
                q.topic.toLowerCase().includes(searchTerm.toLowerCase());

            const matchesSubject =
                subjectFilter === "Todas" || q.subject === subjectFilter;

            const matchesDifficulty =
                difficultyFilter === "Todas" || q.difficulty === difficultyFilter;

            return matchesSearch && matchesSubject && matchesDifficulty;
        });
    }, [searchTerm, subjectFilter, difficultyFilter]);

    // Manejadores de acciones
    const handleView = (question) => {
        console.log("Ver detalle de pregunta:", question);
    };

    const handleEdit = (question) => {
        console.log("Editar pregunta:", question);
    };

    const handleDelete = (question) => {
        console.log("Eliminar / deshabilitar pregunta:", question);
    };

    const handleImportExcel = () => {
        console.log("Acción: Importar preguntas desde Excel / CSV");
    };

    const handleFormSubmit = (e) => {
        e.preventDefault();
        console.log("Nueva pregunta creada (simulado):", formData);
        setIsModalOpen(false);
        // Reset form
        setFormData({
            subject: "Matemáticas",
            topic: "",
            difficulty: "Media",
            statement: "",
            optionA: "",
            optionB: "",
            optionC: "",
            optionD: "",
            correctOption: "A",
            explanation: "",
        });
    };

    return (
        <div className="qst-container">
            {/* 1. HEADER */}
            <header className="qst-header">
                <div className="qst-header-left">
                    <h1 className="qst-title">
                        <i className="fa-solid fa-layer-group"></i>
                        Banco de Preguntas ICFES
                    </h1>
                    <p className="qst-subtitle">
                        Administra las preguntas oficiales de la plataforma.
                    </p>
                </div>
                <button
                    className="qst-btn-new"
                    onClick={() => setIsModalOpen(true)}
                    type="button"
                >
                    <i className="fa-solid fa-plus"></i>
                    <span>+ Nueva pregunta</span>
                </button>
            </header>

            {/* 2. TARJETAS SUPERIORES (KPI STATS) */}
            <section className="qst-stats-grid">
                <div className="qst-stat-card">
                    <div className="qst-stat-info">
                        <span className="qst-stat-label">Total Preguntas</span>
                        <span className="qst-stat-val">5.420</span>
                    </div>
                    <div className="qst-stat-icon-wrap qst-stat-icon--green">
                        <i className="fa-solid fa-folder-open"></i>
                    </div>
                </div>

                <div className="qst-stat-card">
                    <div className="qst-stat-info">
                        <span className="qst-stat-label">Activas</span>
                        <span className="qst-stat-val">5.297</span>
                    </div>
                    <div className="qst-stat-icon-wrap qst-stat-icon--blue">
                        <i className="fa-solid fa-circle-check"></i>
                    </div>
                </div>

                <div className="qst-stat-card">
                    <div className="qst-stat-info">
                        <span className="qst-stat-label">En Revisión</span>
                        <span className="qst-stat-val">98</span>
                    </div>
                    <div className="qst-stat-icon-wrap qst-stat-icon--amber">
                        <i className="fa-solid fa-clock-rotate-left"></i>
                    </div>
                </div>

                <div className="qst-stat-card">
                    <div className="qst-stat-info">
                        <span className="qst-stat-label">Sin Explicación</span>
                        <span className="qst-stat-val">25</span>
                    </div>
                    <div className="qst-stat-icon-wrap qst-stat-icon--purple">
                        <i className="fa-solid fa-comment-dots"></i>
                    </div>
                </div>
            </section>

            {/* 3. BARRA DE HERRAMIENTAS */}
            <section className="qst-tools-bar">
                <div className="qst-search-box">
                    <i className="fa-solid fa-magnifying-glass qst-search-icon"></i>
                    <input
                        type="text"
                        placeholder="Buscar por ID, tema o materia..."
                        className="qst-search-input"
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                    />
                </div>

                <div className="qst-filters-group">
                    <div className="qst-select-wrap">
                        <label className="qst-select-label" htmlFor="filter-materia">
                            Materia:
                        </label>
                        <select
                            id="filter-materia"
                            className="qst-select"
                            value={subjectFilter}
                            onChange={(e) => setSubjectFilter(e.target.value)}
                        >
                            <option value="Todas">Todas</option>
                            <option value="Matemáticas">Matemáticas</option>
                            <option value="Lectura Crítica">Lectura Crítica</option>
                            <option value="Ciencias Naturales">Ciencias Naturales</option>
                            <option value="Sociales y Ciudadanas">Sociales y Ciudadanas</option>
                            <option value="Inglés">Inglés</option>
                        </select>
                    </div>

                    <div className="qst-select-wrap">
                        <label className="qst-select-label" htmlFor="filter-dificultad">
                            Dificultad:
                        </label>
                        <select
                            id="filter-dificultad"
                            className="qst-select"
                            value={difficultyFilter}
                            onChange={(e) => setDifficultyFilter(e.target.value)}
                        >
                            <option value="Todas">Todas</option>
                            <option value="Fácil">Fácil</option>
                            <option value="Media">Media</option>
                            <option value="Difícil">Difícil</option>
                        </select>
                    </div>

                    <button
                        className="qst-btn-excel"
                        onClick={handleImportExcel}
                        type="button"
                        title="Importar preguntas desde archivo Excel"
                    >
                        <i className="fa-solid fa-file-excel"></i>
                        <span>Importar Excel</span>
                    </button>
                </div>
            </section>

            {/* 4. TABLA MODERNA */}
            <section className="qst-table-card">
                <div className="qst-table-responsive">
                    <table className="qst-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Materia</th>
                                <th>Tema</th>
                                <th>Dificultad</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                                <th style={{ textAlign: "center" }}>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filteredQuestions.length > 0 ? (
                                filteredQuestions.map((q) => (
                                    <tr key={q.id}>
                                        {/* ID */}
                                        <td>
                                            <span className="qst-id-tag">#{q.id}</span>
                                        </td>

                                        {/* Materia */}
                                        <td>
                                            <div className="qst-subject-tag">
                                                <span
                                                    className="qst-subject-dot"
                                                    style={{ backgroundColor: q.color }}
                                                ></span>
                                                {q.subject}
                                            </div>
                                        </td>

                                        {/* Tema */}
                                        <td>
                                            <span className="qst-topic-text">{q.topic}</span>
                                        </td>

                                        {/* Dificultad */}
                                        <td>
                                            <span
                                                className={`qst-diff-badge ${
                                                    q.difficulty === "Fácil"
                                                        ? "qst-diff--facil"
                                                        : q.difficulty === "Media"
                                                        ? "qst-diff--media"
                                                        : "qst-diff--dificil"
                                                }`}
                                            >
                                                {q.difficulty}
                                            </span>
                                        </td>

                                        {/* Estado */}
                                        <td>
                                            <span
                                                className={`qst-status-badge ${
                                                    q.status === "Activa"
                                                        ? "qst-status--activa"
                                                        : "qst-status--revision"
                                                }`}
                                            >
                                                <span className="qst-status-dot"></span>
                                                {q.status}
                                            </span>
                                        </td>

                                        {/* Fecha */}
                                        <td style={{ fontSize: "0.8rem", color: "#94a3b8" }}>
                                            {q.date}
                                        </td>

                                        {/* Acciones */}
                                        <td>
                                            <div className="qst-actions-cell" style={{ justifyContent: "center" }}>
                                                <button
                                                    className="qst-action-btn qst-action-btn--view"
                                                    title="Ver pregunta completa"
                                                    type="button"
                                                    onClick={() => handleView(q)}
                                                >
                                                    <i className="fa-solid fa-eye"></i>
                                                </button>
                                                <button
                                                    className="qst-action-btn qst-action-btn--edit"
                                                    title="Editar pregunta"
                                                    type="button"
                                                    onClick={() => handleEdit(q)}
                                                >
                                                    <i className="fa-solid fa-pen-to-square"></i>
                                                </button>
                                                <button
                                                    className="qst-action-btn qst-action-btn--delete"
                                                    title="Eliminar pregunta"
                                                    type="button"
                                                    onClick={() => handleDelete(q)}
                                                >
                                                    <i className="fa-solid fa-trash-can"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan="7">
                                        <div className="qst-empty-state">
                                            <i className="fa-solid fa-layer-group"></i>
                                            <p>No se encontraron preguntas con los filtros seleccionados.</p>
                                        </div>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="qst-table-footer">
                    <span>Mostrando {filteredQuestions.length} de {initialQuestions.length} preguntas cargadas</span>
                    <span>Página 1 de 1</span>
                </div>
            </section>

            {/* 5. MODAL VISUAL: FORMULARIO NUEVA PREGUNTA */}
            {isModalOpen && (
                <div className="qst-modal-overlay" onClick={() => setIsModalOpen(false)}>
                    <div className="qst-modal-card" onClick={(e) => e.stopPropagation()}>
                        <div className="qst-modal-header">
                            <h2 className="qst-modal-title">
                                <i className="fa-solid fa-file-circle-plus"></i>
                                Crear Nueva Pregunta ICFES
                            </h2>
                            <button
                                className="qst-modal-close-btn"
                                onClick={() => setIsModalOpen(false)}
                                aria-label="Cerrar modal"
                            >
                                <i className="fa-solid fa-xmark"></i>
                            </button>
                        </div>

                        <form onSubmit={handleFormSubmit}>
                            <div className="qst-modal-body">
                                {/* Materia, Tema, Dificultad */}
                                <div className="qst-form-grid-3">
                                    <div className="qst-form-group">
                                        <label className="qst-form-label">Materia</label>
                                        <select
                                            className="qst-form-select"
                                            value={formData.subject}
                                            onChange={(e) =>
                                                setFormData({ ...formData, subject: e.target.value })
                                            }
                                        >
                                            <option value="Matemáticas">Matemáticas</option>
                                            <option value="Lectura Crítica">Lectura Crítica</option>
                                            <option value="Ciencias Naturales">Ciencias Naturales</option>
                                            <option value="Sociales y Ciudadanas">Sociales y Ciudadanas</option>
                                            <option value="Inglés">Inglés</option>
                                        </select>
                                    </div>

                                    <div className="qst-form-group">
                                        <label className="qst-form-label">Tema</label>
                                        <input
                                            type="text"
                                            placeholder="Ej: Álgebra y Funciones"
                                            className="qst-form-input"
                                            value={formData.topic}
                                            onChange={(e) =>
                                                setFormData({ ...formData, topic: e.target.value })
                                            }
                                            required
                                        />
                                    </div>

                                    <div className="qst-form-group">
                                        <label className="qst-form-label">Dificultad</label>
                                        <select
                                            className="qst-form-select"
                                            value={formData.difficulty}
                                            onChange={(e) =>
                                                setFormData({ ...formData, difficulty: e.target.value })
                                            }
                                        >
                                            <option value="Fácil">Fácil</option>
                                            <option value="Media">Media</option>
                                            <option value="Difícil">Difícil</option>
                                        </select>
                                    </div>
                                </div>

                                {/* Enunciado */}
                                <div className="qst-form-group">
                                    <label className="qst-form-label">Enunciado de la Pregunta</label>
                                    <textarea
                                        placeholder="Redacta el texto, contexto o situación problema del reactivo ICFES..."
                                        className="qst-form-textarea"
                                        rows="3"
                                        value={formData.statement}
                                        onChange={(e) =>
                                            setFormData({ ...formData, statement: e.target.value })
                                        }
                                        required
                                    ></textarea>
                                </div>

                                {/* Opciones de respuesta */}
                                <div className="qst-options-container">
                                    <h3 className="qst-options-heading">
                                        <i className="fa-solid fa-list-ol"></i> Opciones de Respuesta
                                    </h3>

                                    <div className="qst-form-grid-2">
                                        <div className="qst-form-group">
                                            <label className="qst-form-label">Opción A</label>
                                            <input
                                                type="text"
                                                placeholder="Texto de la opción A"
                                                className="qst-form-input"
                                                value={formData.optionA}
                                                onChange={(e) =>
                                                    setFormData({ ...formData, optionA: e.target.value })
                                                }
                                                required
                                            />
                                        </div>

                                        <div className="qst-form-group">
                                            <label className="qst-form-label">Opción B</label>
                                            <input
                                                type="text"
                                                placeholder="Texto de la opción B"
                                                className="qst-form-input"
                                                value={formData.optionB}
                                                onChange={(e) =>
                                                    setFormData({ ...formData, optionB: e.target.value })
                                                }
                                                required
                                            />
                                        </div>

                                        <div className="qst-form-group">
                                            <label className="qst-form-label">Opción C</label>
                                            <input
                                                type="text"
                                                placeholder="Texto de la opción C"
                                                className="qst-form-input"
                                                value={formData.optionC}
                                                onChange={(e) =>
                                                    setFormData({ ...formData, optionC: e.target.value })
                                                }
                                                required
                                            />
                                        </div>

                                        <div className="qst-form-group">
                                            <label className="qst-form-label">Opción D</label>
                                            <input
                                                type="text"
                                                placeholder="Texto de la opción D"
                                                className="qst-form-input"
                                                value={formData.optionD}
                                                onChange={(e) =>
                                                    setFormData({ ...formData, optionD: e.target.value })
                                                }
                                                required
                                            />
                                        </div>
                                    </div>
                                </div>

                                {/* Respuesta Correcta & Explicación */}
                                <div className="qst-form-grid-2">
                                    <div className="qst-form-group">
                                        <label className="qst-form-label">Respuesta Correcta</label>
                                        <select
                                            className="qst-form-select"
                                            value={formData.correctOption}
                                            onChange={(e) =>
                                                setFormData({ ...formData, correctOption: e.target.value })
                                            }
                                        >
                                            <option value="A">Opción A</option>
                                            <option value="B">Opción B</option>
                                            <option value="C">Opción C</option>
                                            <option value="D">Opción D</option>
                                        </select>
                                    </div>

                                    <div className="qst-form-group">
                                        <label className="qst-form-label">Explicación Pedagógica</label>
                                        <textarea
                                            placeholder="Justificación del por qué es la respuesta correcta..."
                                            className="qst-form-textarea"
                                            rows="2"
                                            value={formData.explanation}
                                            onChange={(e) =>
                                                setFormData({ ...formData, explanation: e.target.value })
                                            }
                                        ></textarea>
                                    </div>
                                </div>
                            </div>

                            <div className="qst-modal-footer">
                                <button
                                    type="button"
                                    className="qst-btn-cancel"
                                    onClick={() => setIsModalOpen(false)}
                                >
                                    Cancelar
                                </button>
                                <button type="submit" className="qst-btn-save">
                                    <i className="fa-solid fa-floppy-disk"></i> Guardar Pregunta
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
};

export default Questions;
