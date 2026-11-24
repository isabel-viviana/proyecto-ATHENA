import { useEffect, useState } from "react";
import "./subjects.css";
import {
    getSubjectsCatalog,
    createSubjectCatalog,
    updateSubjectCatalog,
    deleteSubjectCatalog,
    createTopicCatalog,
    updateTopicCatalog,
    deleteTopicCatalog,
} from "../../api/documents";

const Subjects = () => {
    const [subjects, setSubjects] = useState([]);
    const [selectedSubjectId, setSelectedSubjectId] = useState(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [feedback, setFeedback] = useState(null);

    const [isSubjectModalOpen, setIsSubjectModalOpen] = useState(false);
    const [editingSubject, setEditingSubject] = useState(null);
    const [subjectForm, setSubjectForm] = useState({
        name: "",
        description: "",
        icon: "fa-solid fa-book-bookmark",
        color: "#10b981",
    });

    const [isTopicModalOpen, setIsTopicModalOpen] = useState(false);
    const [editingTopic, setEditingTopic] = useState(null);
    const [topicForm, setTopicForm] = useState({
        name: "",
        description: "",
        is_verified: true,
    });

    const showBanner = (type, text) => {
        setFeedback({ type, text });
        setTimeout(() => {
            setFeedback((prev) => (prev?.text === text ? null : prev));
        }, 5000);
    };

    const loadCatalog = async (preferredId = null) => {
        setLoading(true);
        try {
            const res = await getSubjectsCatalog();
            if (res?.success && Array.isArray(res.data)) {
                setSubjects(res.data);
                if (res.data.length > 0) {
                    const targetId = preferredId || selectedSubjectId || res.data[0].id;
                    const exists = res.data.some((s) => Number(s.id) === Number(targetId));
                    setSelectedSubjectId(exists ? Number(targetId) : Number(res.data[0].id));
                }
            }
        } catch (err) {
            showBanner(
                "error",
                err?.response?.data?.message || "No se pudo consultar el catálogo de áreas y temas en Supabase."
            );
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadCatalog();
    }, []);

    const selectedSubject =
        subjects.find((s) => Number(s.id) === Number(selectedSubjectId)) || subjects[0] || null;

    const handleOpenCreateSubject = () => {
        setEditingSubject(null);
        setSubjectForm({
            name: "",
            description: "",
            icon: "fa-solid fa-book-bookmark",
            color: "#10b981",
        });
        setIsSubjectModalOpen(true);
    };

    const handleOpenEditSubject = (e, subject) => {
        e.stopPropagation();
        setEditingSubject(subject);
        setSubjectForm({
            name: subject.name || "",
            description: subject.description || "",
            icon: subject.icon || "fa-solid fa-book-bookmark",
            color: subject.color || "#10b981",
        });
        setIsSubjectModalOpen(true);
    };

    const handleDeleteSubject = async (e, subject) => {
        e.stopPropagation();
        if (
            !window.confirm(
                `¿Deseas eliminar el área "${subject.name}" del catálogo en Supabase? Solo se eliminará si no tiene preguntas ni estímulos vinculados.`
            )
        ) {
            return;
        }

        try {
            const res = await deleteSubjectCatalog(subject.id);
            showBanner("success", res?.message || "Área eliminada correctamente.");
            await loadCatalog();
            window.dispatchEvent(new Event("athena-admin-stats-refresh"));
        } catch (err) {
            showBanner(
                "error",
                err?.response?.data?.message || "No se pudo eliminar el área académica."
            );
        }
    };

    const handleSaveSubject = async (e) => {
        e.preventDefault();
        if (!subjectForm.name.trim()) return;

        setSaving(true);
        try {
            if (editingSubject) {
                const res = await updateSubjectCatalog(editingSubject.id, {
                    name: subjectForm.name.trim(),
                    description: subjectForm.description.trim(),
                });
                showBanner("success", res?.message || "Área académica actualizada en Supabase.");
                setIsSubjectModalOpen(false);
                await loadCatalog(editingSubject.id);
            } else {
                const res = await createSubjectCatalog({
                    name: subjectForm.name.trim(),
                    description: subjectForm.description.trim(),
                });
                showBanner("success", res?.message || "Área académica creada en Supabase.");
                setIsSubjectModalOpen(false);
                await loadCatalog(res?.data?.id);
            }
            window.dispatchEvent(new Event("athena-admin-stats-refresh"));
        } catch (err) {
            showBanner(
                "error",
                err?.response?.data?.message || "Error al guardar el área académica."
            );
        } finally {
            setSaving(false);
        }
    };

    const handleOpenCreateTopic = () => {
        if (!selectedSubject) return;
        setEditingTopic(null);
        setTopicForm({
            name: "",
            description: "",
            is_verified: true,
        });
        setIsTopicModalOpen(true);
    };

    const handleOpenEditTopic = (topic) => {
        setEditingTopic(topic);
        setTopicForm({
            name: topic.name || "",
            description: topic.description || topic.desc || "",
            is_verified: topic.is_verified !== undefined ? Boolean(topic.is_verified) : true,
        });
        setIsTopicModalOpen(true);
    };

    const handleDeleteTopic = async (topic) => {
        if (
            !window.confirm(
                `¿Deseas eliminar o desactivar el tema "${topic.name}"? Si tiene preguntas vinculadas, se marcará como inactivo sin perder trazabilidad.`
            )
        ) {
            return;
        }

        try {
            const res = await deleteTopicCatalog(topic.id);
            showBanner("success", res?.message || "Tema procesado exitosamente.");
            await loadCatalog(selectedSubject?.id);
            window.dispatchEvent(new Event("athena-admin-stats-refresh"));
        } catch (err) {
            showBanner(
                "error",
                err?.response?.data?.message || "No se pudo eliminar o desactivar el tema."
            );
        }
    };

    const handleSaveTopic = async (e) => {
        e.preventDefault();
        if (!selectedSubject || !topicForm.name.trim()) return;

        setSaving(true);
        try {
            if (editingTopic) {
                const res = await updateTopicCatalog(editingTopic.id, {
                    subject_id: selectedSubject.id,
                    name: topicForm.name.trim(),
                    description: topicForm.description.trim(),
                    is_verified: Boolean(topicForm.is_verified),
                });
                showBanner("success", res?.message || "Tema curricular actualizado en Supabase.");
            } else {
                const res = await createTopicCatalog({
                    subject_id: selectedSubject.id,
                    name: topicForm.name.trim(),
                    description: topicForm.description.trim(),
                    is_verified: Boolean(topicForm.is_verified),
                });
                showBanner("success", res?.message || "Tema curricular creado en Supabase.");
            }
            setIsTopicModalOpen(false);
            await loadCatalog(selectedSubject.id);
            window.dispatchEvent(new Event("athena-admin-stats-refresh"));
        } catch (err) {
            showBanner(
                "error",
                err?.response?.data?.message || "Error al guardar el tema curricular."
            );
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="sbj-container">
            <header className="sbj-header">
                <div className="sbj-header-left">
                    <h1 className="sbj-title">
                        <i className="fa-solid fa-book-bookmark"></i>
                        Gestión de Materias y Temas
                    </h1>
                    <p className="sbj-subtitle">
                        Catálogo oficial en Supabase PostgreSQL — Áreas y temas curriculares para pruebas ICFES Saber 11.
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

            {feedback && (
                <div
                    style={{
                        padding: "12px 16px",
                        borderRadius: "10px",
                        marginBottom: "18px",
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "space-between",
                        background:
                            feedback.type === "error"
                                ? "rgba(239, 68, 68, 0.14)"
                                : "rgba(16, 185, 129, 0.14)",
                        border:
                            feedback.type === "error"
                                ? "1px solid rgba(239, 68, 68, 0.35)"
                                : "1px solid rgba(16, 185, 129, 0.35)",
                        color: feedback.type === "error" ? "#fca5a5" : "#6ee7b7",
                        fontSize: "0.9rem",
                    }}
                >
                    <span>
                        <i
                            className={
                                feedback.type === "error"
                                    ? "fa-solid fa-circle-exclamation"
                                    : "fa-solid fa-circle-check"
                            }
                            style={{ marginRight: "8px" }}
                        ></i>
                        {feedback.text}
                    </span>
                    <button
                        type="button"
                        onClick={() => setFeedback(null)}
                        style={{ background: "none", border: "none", color: "inherit", cursor: "pointer" }}
                    >
                        <i className="fa-solid fa-xmark"></i>
                    </button>
                </div>
            )}

            {loading ? (
                <div
                    style={{
                        padding: "48px",
                        textAlign: "center",
                        color: "#94a3b8",
                        background: "rgba(15, 23, 42, 0.6)",
                        borderRadius: "16px",
                    }}
                >
                    <i className="fa-solid fa-circle-notch fa-spin" style={{ marginRight: "8px" }}></i>
                    Cargando áreas y temas reales desde Supabase PostgreSQL...
                </div>
            ) : (
                <div className="sbj-layout-grid">
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
                                const isSelected = Number(subject.id) === Number(selectedSubject?.id);
                                const totalQuestions = Number(subject.questions_count ?? 0);
                                const publishedQuestions = Number(subject.published_questions_count ?? 0);
                                const topicsList = Array.isArray(subject.topics) ? subject.topics : [];

                                return (
                                    <div
                                        key={subject.id}
                                        className={`sbj-card-item ${isSelected ? "sbj-card-item--active" : ""}`}
                                        onClick={() => setSelectedSubjectId(subject.id)}
                                    >
                                        <div className="sbj-card-info">
                                            <div
                                                className="sbj-card-icon"
                                                style={{
                                                    backgroundColor: `${subject.color || "#10b981"}18`,
                                                    color: subject.color || "#10b981",
                                                }}
                                            >
                                                <i className={subject.icon || "fa-solid fa-book"}></i>
                                            </div>
                                            <div className="sbj-card-texts">
                                                <span className="sbj-card-name">
                                                    {subject.name}{" "}
                                                    <span style={{ fontSize: "0.72rem", color: "#64748b" }}>
                                                        (#{subject.id})
                                                    </span>
                                                </span>
                                                <span className="sbj-card-meta">
                                                    {totalQuestions} preguntas ({publishedQuestions} publicadas)
                                                </span>
                                            </div>
                                        </div>

                                        <div className="sbj-card-actions">
                                            <span className="sbj-card-topics-count">
                                                {topicsList.length} temas
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
                                                onClick={(e) => handleDeleteSubject(e, subject)}
                                            >
                                                <i className="fa-solid fa-trash-can"></i>
                                            </button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    <div className="sbj-right-panel">
                        {selectedSubject && (
                            <>
                                <div className="sbj-selected-header">
                                    <div className="sbj-selected-title-group">
                                        <div
                                            className="sbj-selected-icon-big"
                                            style={{
                                                backgroundColor: `${selectedSubject.color || "#10b981"}20`,
                                                color: selectedSubject.color || "#10b981",
                                                border: `1px solid ${selectedSubject.color || "#10b981"}40`,
                                            }}
                                        >
                                            <i className={selectedSubject.icon || "fa-solid fa-book"}></i>
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

                                {Array.isArray(selectedSubject.topics) && selectedSubject.topics.length > 0 ? (
                                    <div className="sbj-topics-grid">
                                        {selectedSubject.topics.map((topic) => {
                                            const isTopicActive = topic.is_active !== false;
                                            return (
                                                <div
                                                    key={topic.id}
                                                    className="sbj-topic-card"
                                                    style={{
                                                        opacity: isTopicActive ? 1 : 0.65,
                                                    }}
                                                >
                                                    <div className="sbj-topic-top">
                                                        <div style={{ width: "100%" }}>
                                                            <div
                                                                style={{
                                                                    display: "flex",
                                                                    justifyContent: "space-between",
                                                                    alignItems: "center",
                                                                    gap: "8px",
                                                                }}
                                                            >
                                                                <div className="sbj-topic-name">{topic.name}</div>
                                                                <span
                                                                    style={{
                                                                        fontSize: "0.7rem",
                                                                        padding: "2px 8px",
                                                                        borderRadius: "999px",
                                                                        fontWeight: 600,
                                                                        background: isTopicActive
                                                                            ? "rgba(16, 185, 129, 0.15)"
                                                                            : "rgba(239, 68, 68, 0.15)",
                                                                        color: isTopicActive ? "#34d399" : "#f87171",
                                                                    }}
                                                                >
                                                                    {isTopicActive ? "Activo" : "Inactivo"}
                                                                </span>
                                                            </div>
                                                            <div className="sbj-topic-desc">
                                                                {topic.description || topic.desc || "Sin descripción"}
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div className="sbj-topic-footer">
                                                        <span className="sbj-topic-count-pill">
                                                            <i className="fa-solid fa-layer-group"></i>
                                                            {topic.questions_count ?? topic.questionsCount ?? 0} preguntas asociadas
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
                                                                title="Eliminar o desactivar tema"
                                                                type="button"
                                                                onClick={() => handleDeleteTopic(topic)}
                                                            >
                                                                <i className="fa-solid fa-trash-can"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                ) : (
                                    <div className="sbj-empty-topics">
                                        <i className="fa-solid fa-folder-open"></i>
                                        <p>
                                            Esta materia aún no tiene temas registrados en Supabase. Puedes crear los temas curriculares ahora mismo.
                                        </p>
                                        <button
                                            className="sbj-btn-new-tema"
                                            onClick={handleOpenCreateTopic}
                                            style={{ marginTop: "10px" }}
                                            type="button"
                                        >
                                            Crear el primer tema
                                        </button>
                                    </div>
                                )}
                            </>
                        )}
                    </div>
                </div>
            )}

            {isSubjectModalOpen && (
                <div className="sbj-modal-overlay" onClick={() => setIsSubjectModalOpen(false)}>
                    <div className="sbj-modal-card" onClick={(e) => e.stopPropagation()}>
                        <div className="sbj-modal-header">
                            <h3 className="sbj-modal-title">
                                <i className="fa-solid fa-book-bookmark"></i>
                                {editingSubject ? "Editar Materia en Supabase" : "Nueva Materia ICFES"}
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
                                    <label className="sbj-form-label">Nombre del Área Académica *</label>
                                    <input
                                        type="text"
                                        placeholder="Ej: Razonamiento Cuantitativo"
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
                                        placeholder="Resumen del alcance y competencias evaluadas en esta área..."
                                        className="sbj-form-textarea"
                                        value={subjectForm.description}
                                        onChange={(e) =>
                                            setSubjectForm({ ...subjectForm, description: e.target.value })
                                        }
                                    ></textarea>
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
                                <button type="submit" className="sbj-btn-save" disabled={saving}>
                                    <i className="fa-solid fa-check"></i>{" "}
                                    {saving ? "Guardando..." : "Guardar Materia"}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {isTopicModalOpen && (
                <div className="sbj-modal-overlay" onClick={() => setIsTopicModalOpen(false)}>
                    <div className="sbj-modal-card" onClick={(e) => e.stopPropagation()}>
                        <div className="sbj-modal-header">
                            <h3 className="sbj-modal-title">
                                <i className="fa-solid fa-diagram-project"></i>
                                {editingTopic ? "Editar Tema Curricular" : `Nuevo Tema en ${selectedSubject?.name}`}
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
                                    <label className="sbj-form-label">Nombre del Tema *</label>
                                    <input
                                        type="text"
                                        placeholder="Ej: Álgebra y Funciones"
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
                                        placeholder="Conceptos específicos evaluados por el ICFES en este tema..."
                                        className="sbj-form-textarea"
                                        value={topicForm.description}
                                        onChange={(e) =>
                                            setTopicForm({ ...topicForm, description: e.target.value })
                                        }
                                    ></textarea>
                                </div>

                                <div className="sbj-form-group">
                                    <label className="sbj-form-label">Estado del Tema</label>
                                    <select
                                        className="sbj-form-select"
                                        value={topicForm.is_verified ? "true" : "false"}
                                        onChange={(e) =>
                                            setTopicForm({
                                                ...topicForm,
                                                is_verified: e.target.value === "true",
                                            })
                                        }
                                    >
                                        <option value="true">Activo (Disponible en el catálogo)</option>
                                        <option value="false">Inactivo</option>
                                    </select>
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
                                <button type="submit" className="sbj-btn-save" disabled={saving}>
                                    <i className="fa-solid fa-check"></i>{" "}
                                    {saving ? "Guardando..." : "Guardar Tema"}
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
