import { useState } from "react";
import "./subjects.css";

const initialSubjectsData = [
    {
        id: 1,
        name: "Matemáticas",
        description: "Razonamiento cuantitativo, álgebra, cálculo y estadística.",
        icon: "fa-solid fa-calculator",
        color: "#10b981",
        topics: [
            { id: 101, name: "Álgebra y Funciones", desc: "Ecuaciones lineales, cuadráticas y modelos funcionales.", questionsCount: 95 },
            { id: 102, name: "Geometría y Medición", desc: "Teorema de Pitágoras, áreas, volúmenes y trigonometría básica.", questionsCount: 82 },
            { id: 103, name: "Trigonometría", desc: "Razones trigonométricas, ley de senos y cosenos.", questionsCount: 64 },
            { id: 104, name: "Estadística y Probabilidad", desc: "Medidas de tendencia central, dispersión y combinatoria.", questionsCount: 78 },
            { id: 105, name: "Cálculo Diferencial Básico", desc: "Límites, derivadas intuitivas y razón de cambio.", questionsCount: 45 },
        ],
    },
    {
        id: 2,
        name: "Lectura Crítica",
        description: "Comprensión semántica, análisis argumentativo y valoración crítica.",
        icon: "fa-solid fa-book-open",
        color: "#3b82f6",
        topics: [
            { id: 201, name: "Tipología Textual", desc: "Textos continuos (narrativos, expositivos) y discontinuos.", questionsCount: 88 },
            { id: 202, name: "Identificación de Tesis", desc: "Reconocimiento de la idea central y argumentos de apoyo.", questionsCount: 74 },
            { id: 203, name: "Falacias Argumentativas", desc: "Detección de inconsistencias, sesgos y premisas falsas.", questionsCount: 65 },
            { id: 204, name: "Intencionalidad del Autor", desc: "Tono, propósito comunicativo y contexto sociocultural.", questionsCount: 68 },
        ],
    },
    {
        id: 3,
        name: "Ciencias Naturales",
        description: "Indagación científica, uso comprensivo del conocimiento y explicación de fenómenos.",
        icon: "fa-solid fa-flask",
        color: "#a855f7",
        topics: [
            { id: 301, name: "Biología Celular y Genética", desc: "Estructura celular, mitosis, meiosis y herencia mendeliana.", questionsCount: 92 },
            { id: 302, name: "Termodinámica y Química", desc: "Enlaces químicos, estequiometría y leyes de gases.", questionsCount: 84 },
            { id: 303, name: "Cinemática y Dinámica (Física)", desc: "Leyes de Newton, energía, trabajo y conservación.", questionsCount: 76 },
            { id: 304, name: "Ecología y Medio Ambiente", desc: "Cadenas tróficas, ciclos biogeoquímicos y sostenibilidad.", questionsCount: 58 },
        ],
    },
    {
        id: 4,
        name: "Sociales y Ciudadanas",
        description: "Pensamiento social, interpretación de perspectivas y pensamiento reflexivo.",
        icon: "fa-solid fa-landmark",
        color: "#f59e0b",
        topics: [
            { id: 401, name: "Constitución Política de 1991", desc: "Derechos fundamentales, ramas del poder y tutela.", questionsCount: 75 },
            { id: 402, name: "Historia de Colombia y Conflicto", desc: "Siglo XX, violencia partidista y procesos de paz.", questionsCount: 68 },
            { id: 403, name: "Geografía y Economía", desc: "Globalización, modelos económicos y territorio nacional.", questionsCount: 62 },
            { id: 404, name: "Competencias Ciudadanas", desc: "Mecanismos de participación, pluralismo y convivencia.", questionsCount: 55 },
        ],
    },
    {
        id: 5,
        name: "Inglés",
        description: "Competencia comunicativa y lectora en lengua extranjera (Nivel A1 a B1+).",
        icon: "fa-solid fa-earth-americas",
        color: "#06b6d4",
        topics: [
            { id: 501, name: "Vocabulario y Avisos Cotidianos", desc: "Reconocimiento de avisos y emparejamiento de definiciones.", questionsCount: 60 },
            { id: 502, name: "Conversaciones Completas", desc: "Diálogos cortos, respuestas coherentes y situaciones diarias.", questionsCount: 54 },
            { id: 503, name: "Lectura Literal e Inferencial B1", desc: "Artículos cortos, biografías y comprensión de artículos.", questionsCount: 52 },
            { id: 504, name: "Gramática en Contexto (Cloze Test)", desc: "Tiempos verbales, conectores y vocabulario estructural.", questionsCount: 49 },
        ],
    },
];

const Subjects = () => {
    const [subjects, setSubjects] = useState(initialSubjectsData);
    const [selectedSubjectId, setSelectedSubjectId] = useState(1);

    // Modales y Edición
    const [isSubjectModalOpen, setIsSubjectModalOpen] = useState(false);
    const [editingSubject, setEditingSubject] = useState(null);
    const [subjectForm, setSubjectForm] = useState({ name: "", description: "", icon: "fa-solid fa-book", color: "#10b981" });

    const [isTopicModalOpen, setIsTopicModalOpen] = useState(false);
    const [editingTopic, setEditingTopic] = useState(null);
    const [topicForm, setTopicForm] = useState({ name: "", desc: "", questionsCount: 50 });

    // Materia Seleccionada actual
    const selectedSubject = subjects.find((s) => s.id === selectedSubjectId) || subjects[0];

    // =========================================================
    // HANDLERS MATERIAS
    // =========================================================
    const handleOpenCreateSubject = () => {
        setEditingSubject(null);
        setSubjectForm({ name: "", description: "", icon: "fa-solid fa-book-bookmark", color: "#10b981" });
        setIsSubjectModalOpen(true);
    };

    const handleOpenEditSubject = (e, subject) => {
        e.stopPropagation();
        setEditingSubject(subject);
        setSubjectForm({ name: subject.name, description: subject.description, icon: subject.icon, color: subject.color });
        setIsSubjectModalOpen(true);
    };

    const handleDeleteSubject = (e, subjectId) => {
        e.stopPropagation();
        if (subjects.length <= 1) {
            alert("Debe haber al menos una materia registrada.");
            return;
        }
        const updated = subjects.filter((s) => s.id !== subjectId);
        setSubjects(updated);
        if (selectedSubjectId === subjectId) {
            setSelectedSubjectId(updated[0].id);
        }
    };

    const handleSaveSubject = (e) => {
        e.preventDefault();
        if (editingSubject) {
            // Editar existente
            setSubjects((prev) =>
                prev.map((s) =>
                    s.id === editingSubject.id
                        ? { ...s, name: subjectForm.name, description: subjectForm.description, icon: subjectForm.icon, color: subjectForm.color }
                        : s
                )
            );
        } else {
            // Crear nueva
            const newId = Date.now();
            const newSubject = {
                id: newId,
                name: subjectForm.name,
                description: subjectForm.description,
                icon: subjectForm.icon,
                color: subjectForm.color,
                topics: [],
            };
            setSubjects([...subjects, newSubject]);
            setSelectedSubjectId(newId);
        }
        setIsSubjectModalOpen(false);
    };

    // =========================================================
    // HANDLERS TEMAS
    // =========================================================
    const handleOpenCreateTopic = () => {
        setEditingTopic(null);
        setTopicForm({ name: "", desc: "", questionsCount: 40 });
        setIsTopicModalOpen(true);
    };

    const handleOpenEditTopic = (topic) => {
        setEditingTopic(topic);
        setTopicForm({ name: topic.name, desc: topic.desc, questionsCount: topic.questionsCount });
        setIsTopicModalOpen(true);
    };

    const handleDeleteTopic = (topicId) => {
        setSubjects((prev) =>
            prev.map((s) =>
                s.id === selectedSubjectId
                    ? { ...s, topics: s.topics.filter((t) => t.id !== topicId) }
                    : s
            )
        );
    };

    const handleSaveTopic = (e) => {
        e.preventDefault();
        setSubjects((prev) =>
            prev.map((s) => {
                if (s.id !== selectedSubjectId) return s;

                if (editingTopic) {
                    // Editar tema
                    return {
                        ...s,
                        topics: s.topics.map((t) =>
                            t.id === editingTopic.id
                                ? { ...t, name: topicForm.name, desc: topicForm.desc, questionsCount: Number(topicForm.questionsCount) }
                                : t
                        ),
                    };
                } else {
                    // Crear nuevo tema
                    const newTopic = {
                        id: Date.now(),
                        name: topicForm.name,
                        desc: topicForm.desc,
                        questionsCount: Number(topicForm.questionsCount),
                    };
                    return {
                        ...s,
                        topics: [...s.topics, newTopic],
                    };
                }
            })
        );
        setIsTopicModalOpen(false);
    };

    return (
        <div className="sbj-container">
            {/* 1. HEADER */}
            <header className="sbj-header">
                <div className="sbj-header-left">
                    <h1 className="sbj-title">
                        <i className="fa-solid fa-book-bookmark"></i>
                        Gestión de Materias y Temas
                    </h1>
                    <p className="sbj-subtitle">
                        Estructura académica oficial y taxonomía para las pruebas ICFES Saber 11.
                    </p>
                </div>
                <button
                    className="sbj-btn-new-materia"
                    onClick={handleOpenCreateSubject}
                    type="button"
                >
                    <i className="fa-solid fa-plus"></i>
                    <span>+ Nueva Materia</span>
                </button>
            </header>

            {/* 2. MAIN SPLIT LAYOUT */}
            <div className="sbj-layout-grid">
                {/* PANEL IZQUIERDO: LISTADO DE MATERIAS */}
                <div className="sbj-left-panel">
                    <div className="sbj-panel-heading">
                        <h2 className="sbj-panel-heading-title">
                            <i className="fa-solid fa-layer-group"></i>
                            Materias ICFES
                        </h2>
                        <span className="sbj-panel-badge">{subjects.length} Registradas</span>
                    </div>

                    <div className="sbj-cards-list">
                        {subjects.map((subject) => {
                            const isSelected = subject.id === selectedSubjectId;
                            const totalQuestions = subject.topics.reduce(
                                (acc, t) => acc + (t.questionsCount || 0),
                                0
                            );

                            return (
                                <div
                                    key={subject.id}
                                    className={`sbj-card-item ${isSelected ? "sbj-card-item--active" : ""}`}
                                    onClick={() => setSelectedSubjectId(subject.id)}
                                >
                                    <div className="sbj-card-info">
                                        <div
                                            className="sbj-card-icon"
                                            style={{ backgroundColor: `${subject.color}18`, color: subject.color }}
                                        >
                                            <i className={subject.icon}></i>
                                        </div>
                                        <div className="sbj-card-texts">
                                            <span className="sbj-card-name">{subject.name}</span>
                                            <span className="sbj-card-meta">
                                                {totalQuestions} preguntas registradas
                                            </span>
                                        </div>
                                    </div>

                                    <div className="sbj-card-actions">
                                        <span className="sbj-card-topics-count">
                                            {subject.topics.length} temas
                                        </span>
                                        <button
                                            className="sbj-mini-action-btn"
                                            title="Editar materia"
                                            type="button"
                                            onClick={(e) => handleOpenEditSubject(e, subject)}
                                        >
                                            <i className="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button
                                            className="sbj-mini-action-btn sbj-mini-action-btn--delete"
                                            title="Eliminar materia"
                                            type="button"
                                            onClick={(e) => handleDeleteSubject(e, subject.id)}
                                        >
                                            <i className="fa-solid fa-trash-can"></i>
                                        </button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* PANEL DERECHO: TEMAS DE LA MATERIA SELECCIONADA */}
                <div className="sbj-right-panel">
                    {selectedSubject && (
                        <>
                            <div className="sbj-selected-header">
                                <div className="sbj-selected-title-group">
                                    <div
                                        className="sbj-selected-icon-big"
                                        style={{
                                            backgroundColor: `${selectedSubject.color}20`,
                                            color: selectedSubject.color,
                                            border: `1px solid ${selectedSubject.color}40`,
                                        }}
                                    >
                                        <i className={selectedSubject.icon}></i>
                                    </div>
                                    <div className="sbj-selected-titles">
                                        <h2 className="sbj-selected-name">{selectedSubject.name}</h2>
                                        <span className="sbj-selected-sub">
                                            {selectedSubject.description}
                                        </span>
                                    </div>
                                </div>

                                <button
                                    className="sbj-btn-new-tema"
                                    onClick={handleOpenCreateTopic}
                                    type="button"
                                >
                                    <i className="fa-solid fa-plus"></i>
                                    <span>+ Agregar Tema</span>
                                </button>
                            </div>

                            {/* Cuadrícula de Temas */}
                            {selectedSubject.topics.length > 0 ? (
                                <div className="sbj-topics-grid">
                                    {selectedSubject.topics.map((topic) => (
                                        <div key={topic.id} className="sbj-topic-card">
                                            <div className="sbj-topic-top">
                                                <div>
                                                    <div className="sbj-topic-name">{topic.name}</div>
                                                    <div className="sbj-topic-desc">{topic.desc}</div>
                                                </div>
                                            </div>

                                            <div className="sbj-topic-footer">
                                                <span className="sbj-topic-count-pill">
                                                    <i className="fa-solid fa-layer-group"></i>
                                                    {topic.questionsCount} preguntas
                                                </span>

                                                <div className="sbj-topic-actions">
                                                    <button
                                                        className="sbj-topic-action-btn"
                                                        title="Editar tema"
                                                        type="button"
                                                        onClick={() => handleOpenEditTopic(topic)}
                                                    >
                                                        <i className="fa-solid fa-pen-to-square"></i>
                                                    </button>
                                                    <button
                                                        className="sbj-topic-action-btn sbj-topic-action-btn--delete"
                                                        title="Eliminar tema"
                                                        type="button"
                                                        onClick={() => handleDeleteTopic(topic.id)}
                                                    >
                                                        <i className="fa-solid fa-trash-can"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="sbj-empty-topics">
                                    <i className="fa-solid fa-folder-open"></i>
                                    <p>Esta materia aún no tiene temas asignados.</p>
                                    <button
                                        className="sbj-btn-new-tema"
                                        onClick={handleOpenCreateTopic}
                                        style={{ marginTop: "10px" }}
                                    >
                                        Crear el primer tema
                                    </button>
                                </div>
                            )}
                        </>
                    )}
                </div>
            </div>

            {/* =========================================================
               MODAL: CREAR / EDITAR MATERIA
               ========================================================= */}
            {isSubjectModalOpen && (
                <div className="sbj-modal-overlay" onClick={() => setIsSubjectModalOpen(false)}>
                    <div className="sbj-modal-card" onClick={(e) => e.stopPropagation()}>
                        <div className="sbj-modal-header">
                            <h3 className="sbj-modal-title">
                                <i className="fa-solid fa-book-bookmark"></i>
                                {editingSubject ? "Editar Materia" : "Nueva Materia ICFES"}
                            </h3>
                            <button
                                className="sbj-modal-close-btn"
                                onClick={() => setIsSubjectModalOpen(false)}
                                aria-label="Cerrar modal"
                            >
                                <i className="fa-solid fa-xmark"></i>
                            </button>
                        </div>

                        <form onSubmit={handleSaveSubject}>
                            <div className="sbj-modal-body">
                                <div className="sbj-form-group">
                                    <label className="sbj-form-label">Nombre de la Materia</label>
                                    <input
                                        type="text"
                                        placeholder="Ej: Matemáticas"
                                        className="sbj-form-input"
                                        value={subjectForm.name}
                                        onChange={(e) =>
                                            setSubjectForm({ ...subjectForm, name: e.target.value })
                                        }
                                        required
                                        autoFocus
                                    />
                                </div>

                                <div className="sbj-form-group">
                                    <label className="sbj-form-label">Descripción Académica</label>
                                    <textarea
                                        placeholder="Resumen del alcance y competencias evaluadas..."
                                        className="sbj-form-textarea"
                                        value={subjectForm.description}
                                        onChange={(e) =>
                                            setSubjectForm({ ...subjectForm, description: e.target.value })
                                        }
                                        required
                                    ></textarea>
                                </div>

                                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "12px" }}>
                                    <div className="sbj-form-group">
                                        <label className="sbj-form-label">Icono (FontAwesome)</label>
                                        <select
                                            className="sbj-form-select"
                                            value={subjectForm.icon}
                                            onChange={(e) =>
                                                setSubjectForm({ ...subjectForm, icon: e.target.value })
                                            }
                                        >
                                            <option value="fa-solid fa-calculator">Calculadora</option>
                                            <option value="fa-solid fa-book-open">Libro</option>
                                            <option value="fa-solid fa-flask">Matraz / Ciencia</option>
                                            <option value="fa-solid fa-landmark">Monumento / Sociales</option>
                                            <option value="fa-solid fa-earth-americas">Planeta / Inglés</option>
                                            <option value="fa-solid fa-atom">Átomo</option>
                                            <option value="fa-solid fa-pen-nib">Pluma</option>
                                        </select>
                                    </div>

                                    <div className="sbj-form-group">
                                        <label className="sbj-form-label">Color Temático</label>
                                        <select
                                            className="sbj-form-select"
                                            value={subjectForm.color}
                                            onChange={(e) =>
                                                setSubjectForm({ ...subjectForm, color: e.target.value })
                                            }
                                        >
                                            <option value="#10b981">Verde Esmeralda</option>
                                            <option value="#3b82f6">Azul Royal</option>
                                            <option value="#a855f7">Púrpura</option>
                                            <option value="#f59e0b">Ámbar / Naranja</option>
                                            <option value="#06b6d4">Cian</option>
                                            <option value="#ec4899">Rosa / Magenta</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div className="sbj-modal-footer">
                                <button
                                    type="button"
                                    className="sbj-btn-cancel"
                                    onClick={() => setIsSubjectModalOpen(false)}
                                >
                                    Cancelar
                                </button>
                                <button type="submit" className="sbj-btn-save">
                                    <i className="fa-solid fa-check"></i> Guardar Materia
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* =========================================================
               MODAL: CREAR / EDITAR TEMA
               ========================================================= */}
            {isTopicModalOpen && (
                <div className="sbj-modal-overlay" onClick={() => setIsTopicModalOpen(false)}>
                    <div className="sbj-modal-card" onClick={(e) => e.stopPropagation()}>
                        <div className="sbj-modal-header">
                            <h3 className="sbj-modal-title">
                                <i className="fa-solid fa-diagram-project"></i>
                                {editingTopic ? "Editar Tema" : `Nuevo Tema en ${selectedSubject?.name}`}
                            </h3>
                            <button
                                className="sbj-modal-close-btn"
                                onClick={() => setIsTopicModalOpen(false)}
                                aria-label="Cerrar modal"
                            >
                                <i className="fa-solid fa-xmark"></i>
                            </button>
                        </div>

                        <form onSubmit={handleSaveTopic}>
                            <div className="sbj-modal-body">
                                <div className="sbj-form-group">
                                    <label className="sbj-form-label">Nombre del Tema</label>
                                    <input
                                        type="text"
                                        placeholder="Ej: Álgebra Lineal y Matrices"
                                        className="sbj-form-input"
                                        value={topicForm.name}
                                        onChange={(e) =>
                                            setTopicForm({ ...topicForm, name: e.target.value })
                                        }
                                        required
                                        autoFocus
                                    />
                                </div>

                                <div className="sbj-form-group">
                                    <label className="sbj-form-label">Competencias / Descripción</label>
                                    <textarea
                                        placeholder="Conceptos específicos evaluados por el ICFES..."
                                        className="sbj-form-textarea"
                                        value={topicForm.desc}
                                        onChange={(e) =>
                                            setTopicForm({ ...topicForm, desc: e.target.value })
                                        }
                                        required
                                    ></textarea>
                                </div>

                                <div className="sbj-form-group">
                                    <label className="sbj-form-label">Cantidad Inicial de Preguntas</label>
                                    <input
                                        type="number"
                                        min="0"
                                        placeholder="40"
                                        className="sbj-form-input"
                                        value={topicForm.questionsCount}
                                        onChange={(e) =>
                                            setTopicForm({ ...topicForm, questionsCount: e.target.value })
                                        }
                                        required
                                    />
                                </div>
                            </div>

                            <div className="sbj-modal-footer">
                                <button
                                    type="button"
                                    className="sbj-btn-cancel"
                                    onClick={() => setIsTopicModalOpen(false)}
                                >
                                    Cancelar
                                </button>
                                <button type="submit" className="sbj-btn-save">
                                    <i className="fa-solid fa-check"></i> Guardar Tema
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
};

export default Subjects;
