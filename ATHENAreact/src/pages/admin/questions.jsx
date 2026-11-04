import { useState, useMemo, useEffect, useCallback, useRef } from "react";
import "./questions.css";
import {
    getDocuments,
    uploadDocument,
    getDocument,
    deleteDocument,
} from "../../api/documents";

// Datos iniciales de simulación para la pestaña de preguntas manuales
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
    // =========================================================
    // 1. ESTADO DE PESTAÑAS (TABS)
    // =========================================================
    const [activeTab, setActiveTab] = useState("documents"); // "questions", "documents", "ai_extraction"

    // =========================================================
    // 2. ESTADOS: BANCO DE PREGUNTAS (TAB 1)
    // =========================================================
    const [searchTerm, setSearchTerm] = useState("");
    const [subjectFilter, setSubjectFilter] = useState("Todas");
    const [difficultyFilter, setDifficultyFilter] = useState("Todas");
    const [isModalOpen, setIsModalOpen] = useState(false);

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

    // =========================================================
    // 3. ESTADOS: CUADERNILLOS Y DOCUMENTOS PDF (TAB 2)
    // =========================================================
    const [documents, setDocuments] = useState([]);
    const [isLoadingDocs, setIsLoadingDocs] = useState(false);
    const [docError, setDocError] = useState(null);
    const [docSuccess, setDocSuccess] = useState(null);

    // Filtros de documentos
    const [docSearch, setDocSearch] = useState("");
    const [docYearFilter, setDocYearFilter] = useState("Todos");
    const [docStatusFilter, setDocStatusFilter] = useState("Todos");

    // Modales de documentos
    const [isUploadModalOpen, setIsUploadModalOpen] = useState(false);
    const [isDetailModalOpen, setIsDetailModalOpen] = useState(false);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [selectedDoc, setSelectedDoc] = useState(null);
    const [docDetailData, setDocDetailData] = useState(null);

    // Formulario de subida de PDF
    const [uploadTitle, setUploadTitle] = useState("");
    const [uploadYear, setUploadYear] = useState(new Date().getFullYear());
    const [uploadExamType, setUploadExamType] = useState("Saber 11");
    const [uploadHasAnswerKey, setUploadHasAnswerKey] = useState(true);
    const [uploadTotalPages, setUploadTotalPages] = useState("");
    const [selectedPdfFile, setSelectedPdfFile] = useState(null);
    const [isUploading, setIsUploading] = useState(false);
    const [uploadProgress, setUploadProgress] = useState(0);

    // Estado para confirmación de eliminación
    const [isDeleting, setIsDeleting] = useState(false);

    const fileInputRef = useRef(null);

    // =========================================================
    // 4. CARGA DE DOCUMENTOS DESDE EL BACKEND
    // =========================================================
    const fetchDocuments = useCallback(async () => {
        setIsLoadingDocs(true);
        setDocError(null);
        try {
            const params = {};
            if (docSearch.trim()) params.search = docSearch.trim();
            if (docYearFilter !== "Todos") params.year = docYearFilter;
            if (docStatusFilter !== "Todos") params.status = docStatusFilter;

            const response = await getDocuments(params);
            if (response && response.success) {
                setDocuments(response.data || []);
            } else {
                setDocuments([]);
            }
        } catch (err) {
            console.error("Error al cargar documentos:", err);
            setDocError(
                err.response?.data?.message ||
                "No fue posible conectar con el microservicio Question Bank (puerto 8001). Verifique que el servicio esté activo."
            );
        } finally {
            setIsLoadingDocs(false);
        }
    }, [docSearch, docYearFilter, docStatusFilter]);

    useEffect(() => {
        if (activeTab === "documents") {
            fetchDocuments();
        }
    }, [activeTab, fetchDocuments]);

    // =========================================================
    // 5. MANEJADORES: SUBIDA DE DOCUMENTOS
    // =========================================================
    const handleFileChange = (e) => {
        const file = e.target.files?.[0];
        if (!file) return;

        if (file.type !== "application/pdf" && !file.name.toLowerCase().endsWith(".pdf")) {
            setDocError("Únicamente se permiten archivos con formato PDF.");
            setSelectedPdfFile(null);
            return;
        }

        setSelectedPdfFile(file);
        if (!uploadTitle.trim()) {
            // Sugerir título amigable a partir del nombre del archivo
            const base = file.name.replace(/\.[^/.]+$/, "").replace(/[-_]/g, " ");
            setUploadTitle(base.charAt(0).toUpperCase() + base.slice(1));
        }
    };

    const handleUploadSubmit = async (e) => {
        e.preventDefault();
        if (!selectedPdfFile) {
            setDocError("Debe seleccionar un archivo PDF.");
            return;
        }

        setIsUploading(true);
        setUploadProgress(0);
        setDocError(null);

        try {
            const payload = new FormData();
            payload.append("file", selectedPdfFile);
            payload.append("title", uploadTitle.trim());
            if (uploadYear) payload.append("year", uploadYear);
            if (uploadExamType) payload.append("exam_type", uploadExamType);
            payload.append("has_answer_key", uploadHasAnswerKey ? "1" : "0");
            if (uploadTotalPages) payload.append("total_pages", uploadTotalPages);

            await uploadDocument(payload, (progressEvent) => {
                if (progressEvent.total) {
                    const pct = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                    setUploadProgress(pct);
                }
            });

            setDocSuccess("¡Cuadernillo PDF subido y registrado exitosamente en Supabase Storage!");
            setIsUploadModalOpen(false);

            // Reset form
            setUploadTitle("");
            setSelectedPdfFile(null);
            setUploadTotalPages("");
            if (fileInputRef.current) fileInputRef.current.value = "";

            // Refrescar lista
            await fetchDocuments();
        } catch (err) {
            console.error("Error al subir documento:", err);
            const msg =
                err.response?.data?.errors?.file?.[0] ||
                err.response?.data?.message ||
                "Error al procesar la subida del documento.";
            setDocError(msg);
        } finally {
            setIsUploading(false);
            setUploadProgress(0);
        }
    };

    // =========================================================
    // 6. MANEJADORES: CONSULTA Y DETALLES CON URL FIRMADA
    // =========================================================
    const handleOpenDetail = async (doc) => {
        setSelectedDoc(doc);
        setDocDetailData(null);
        setIsDetailModalOpen(true);

        try {
            const res = await getDocument(doc.id, true);
            if (res && res.success) {
                setDocDetailData(res.data);
            }
        } catch (err) {
            console.error("Error al obtener detalle del documento:", err);
        }
    };

    const handleDirectOpenPdf = async (doc) => {
        try {
            const res = await getDocument(doc.id, true);
            if (res?.data?.temporary_url) {
                window.open(res.data.temporary_url, "_blank", "noopener,noreferrer");
            } else {
                setDocError("No se pudo obtener la URL temporal firmada del documento.");
            }
        } catch (err) {
            console.error("Error al abrir PDF:", err);
            setDocError("Error al generar la URL temporal firmada.");
        }
    };

    // =========================================================
    // 7. MANEJADORES: ELIMINACIÓN DE DOCUMENTOS
    // =========================================================
    const handlePromptDelete = (doc) => {
        setSelectedDoc(doc);
        setIsDeleteModalOpen(true);
    };

    const handleConfirmDelete = async () => {
        if (!selectedDoc) return;
        setIsDeleting(true);
        setDocError(null);

        try {
            await deleteDocument(selectedDoc.id);
            setDocSuccess(`El documento "${selectedDoc.title}" fue eliminado de Supabase Storage y de la base de datos.`);
            setIsDeleteModalOpen(false);
            setSelectedDoc(null);
            await fetchDocuments();
        } catch (err) {
            console.error("Error al eliminar documento:", err);
            const msg =
                err.response?.data?.message ||
                "No fue posible eliminar el documento. Verifique que no tenga preguntas asociadas.";
            setDocError(msg);
        } finally {
            setIsDeleting(false);
        }
    };

    // =========================================================
    // 8. HANDLERS PREGUNTAS (TAB 1)
    // =========================================================
    const handleView = (q) => console.log("Ver detalle:", q);
    const handleEdit = (q) => console.log("Editar:", q);
    const handleDelete = (q) => console.log("Eliminar:", q);

    const handleFormSubmit = (e) => {
        e.preventDefault();
        setIsModalOpen(false);
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
            {/* 1. HEADER PRINCIPAL */}
            <header className="qst-header">
                <div className="qst-header-left">
                    <h1 className="qst-title">
                        <i className="fa-solid fa-layer-group"></i>
                        Banco de Preguntas & Cuadernillos ICFES
                    </h1>
                    <p className="qst-subtitle">
                        Administración centralizada de documentos fuente, reactivos oficiales y calibración pedagógica.
                    </p>
                </div>
                {activeTab === "documents" ? (
                    <button
                        className="qst-btn-new"
                        onClick={() => setIsUploadModalOpen(true)}
                        type="button"
                    >
                        <i className="fa-solid fa-file-arrow-up"></i>
                        <span>+ Cargar Cuadernillo PDF</span>
                    </button>
                ) : activeTab === "questions" ? (
                    <button
                        className="qst-btn-new"
                        onClick={() => setIsModalOpen(true)}
                        type="button"
                    >
                        <i className="fa-solid fa-plus"></i>
                        <span>+ Nueva pregunta</span>
                    </button>
                ) : null}
            </header>

            {/* 2. BARRA DE NAVEGACIÓN POR PESTAÑAS (TABS) */}
            <nav className="qst-nav-tabs" aria-label="Secciones del Banco de Preguntas">
                <button
                    type="button"
                    className={`qst-tab-btn ${activeTab === "documents" ? "qst-tab-btn--active" : ""}`}
                    onClick={() => setActiveTab("documents")}
                >
                    <i className="fa-solid fa-file-pdf"></i>
                    <span>Cuadernillos y Documentos PDF</span>
                    <span className="qst-tab-badge">{documents.length}</span>
                </button>

                <button
                    type="button"
                    className={`qst-tab-btn ${activeTab === "questions" ? "qst-tab-btn--active" : ""}`}
                    onClick={() => setActiveTab("questions")}
                >
                    <i className="fa-solid fa-layer-group"></i>
                    <span>Banco de Preguntas</span>
                    <span className="qst-tab-badge">5.4k</span>
                </button>

                <button
                    type="button"
                    className={`qst-tab-btn ${activeTab === "ai_extraction" ? "qst-tab-btn--active" : ""}`}
                    onClick={() => setActiveTab("ai_extraction")}
                >
                    <i className="fa-solid fa-wand-magic-sparkles"></i>
                    <span>Extracción y Revisión IA</span>
                    <span className="qst-tab-badge qst-tab-badge--muted">Fase 3</span>
                </button>
            </nav>

            {/* MENSAJES DE ESTADO (TOASTS / ALERTS) */}
            {docSuccess && (
                <div className="qst-alert qst-alert--success">
                    <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
                        <i className="fa-solid fa-circle-check"></i>
                        <span>{docSuccess}</span>
                    </div>
                    <button
                        className="qst-alert-close"
                        onClick={() => setDocSuccess(null)}
                        aria-label="Cerrar notificación"
                    >
                        <i className="fa-solid fa-xmark"></i>
                    </button>
                </div>
            )}

            {docError && (
                <div className="qst-alert qst-alert--error">
                    <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
                        <i className="fa-solid fa-triangle-exclamation"></i>
                        <span>{docError}</span>
                    </div>
                    <button
                        className="qst-alert-close"
                        onClick={() => setDocError(null)}
                        aria-label="Cerrar error"
                    >
                        <i className="fa-solid fa-xmark"></i>
                    </button>
                </div>
            )}

            {/* =========================================================
                PESTAÑA 2: CUADERNILLOS Y DOCUMENTOS PDF (CONECTADA A LARAVEL)
                ========================================================= */}
            {activeTab === "documents" && (
                <>
                    {/* TARJETAS KPI DE DOCUMENTOS */}
                    <section className="qst-stats-grid">
                        <div className="qst-stat-card">
                            <div className="qst-stat-info">
                                <span className="qst-stat-label">Total Cuadernillos</span>
                                <span className="qst-stat-val">{documents.length}</span>
                            </div>
                            <div className="qst-stat-icon-wrap qst-stat-icon--green">
                                <i className="fa-solid fa-book-bookmark"></i>
                            </div>
                        </div>

                        <div className="qst-stat-card">
                            <div className="qst-stat-info">
                                <span className="qst-stat-label">Almacenamiento Seguro</span>
                                <span className="qst-stat-val">icfes-documents</span>
                            </div>
                            <div className="qst-stat-icon-wrap qst-stat-icon--blue">
                                <i className="fa-solid fa-shield-halved"></i>
                            </div>
                        </div>

                        <div className="qst-stat-card">
                            <div className="qst-stat-info">
                                <span className="qst-stat-label">Microservicio Activo</span>
                                <span className="qst-stat-val">Puerto 8001</span>
                            </div>
                            <div className="qst-stat-icon-wrap qst-stat-icon--purple">
                                <i className="fa-solid fa-network-wired"></i>
                            </div>
                        </div>

                        <div className="qst-stat-card">
                            <div className="qst-stat-info">
                                <span className="qst-stat-label">Con Clave de Respuesta</span>
                                <span className="qst-stat-val">
                                    {documents.filter((d) => d.has_answer_key).length}
                                </span>
                            </div>
                            <div className="qst-stat-icon-wrap qst-stat-icon--amber">
                                <i className="fa-solid fa-key"></i>
                            </div>
                        </div>
                    </section>

                    {/* BARRA DE HERRAMIENTAS Y FILTROS */}
                    <section className="qst-tools-bar">
                        <div className="qst-search-box">
                            <i className="fa-solid fa-magnifying-glass qst-search-icon"></i>
                            <input
                                type="text"
                                placeholder="Buscar cuadernillo por título..."
                                className="qst-search-input"
                                value={docSearch}
                                onChange={(e) => setDocSearch(e.target.value)}
                            />
                        </div>

                        <div className="qst-filters-group">
                            <div className="qst-select-wrap">
                                <label className="qst-select-label" htmlFor="filter-doc-year">
                                    Año:
                                </label>
                                <select
                                    id="filter-doc-year"
                                    className="qst-select"
                                    value={docYearFilter}
                                    onChange={(e) => setDocYearFilter(e.target.value)}
                                >
                                    <option value="Todos">Todos</option>
                                    <option value="2026">2026</option>
                                    <option value="2025">2025</option>
                                    <option value="2024">2024</option>
                                    <option value="2023">2023</option>
                                    <option value="2022">2022</option>
                                    <option value="2021">2021</option>
                                    <option value="2020">2020</option>
                                </select>
                            </div>

                            <div className="qst-select-wrap">
                                <label className="qst-select-label" htmlFor="filter-doc-status">
                                    Estado:
                                </label>
                                <select
                                    id="filter-doc-status"
                                    className="qst-select"
                                    value={docStatusFilter}
                                    onChange={(e) => setDocStatusFilter(e.target.value)}
                                >
                                    <option value="Todos">Todos</option>
                                    <option value="uploaded">Subido (uploaded)</option>
                                    <option value="processing">En Proceso</option>
                                    <option value="extracted">Extraído</option>
                                    <option value="reviewed">Revisado</option>
                                    <option value="failed">Fallido</option>
                                </select>
                            </div>

                            <button
                                className="qst-btn-excel"
                                onClick={fetchDocuments}
                                type="button"
                                title="Recargar lista desde el servidor"
                                disabled={isLoadingDocs}
                            >
                                <i className={`fa-solid fa-arrows-rotate ${isLoadingDocs ? "fa-spin" : ""}`}></i>
                                <span>Refrescar</span>
                            </button>
                        </div>
                    </section>

                    {/* TABLA DE CUADERNILLOS REALES */}
                    <section className="qst-table-card">
                        <div className="qst-table-responsive">
                            <table className="qst-table">
                                <thead>
                                    <tr>
                                        <th>Título del Cuadernillo</th>
                                        <th>Año</th>
                                        <th>Convocatoria</th>
                                        <th>Respuestas</th>
                                        <th>Estado</th>
                                        <th>Fecha Registro</th>
                                        <th style={{ textAlign: "center" }}>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {isLoadingDocs ? (
                                        <tr>
                                            <td colSpan="7" style={{ textAlign: "center", padding: "40px" }}>
                                                <div style={{ display: "flex", flexDirection: "column", alignItems: "center", gap: "12px" }}>
                                                    <span className="qst-spinner"></span>
                                                    <span style={{ color: "#94a3b8", fontSize: "0.9rem" }}>
                                                        Consultando cuadernillos en Supabase PostgreSQL...
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    ) : documents.length > 0 ? (
                                        documents.map((doc) => (
                                            <tr key={doc.id}>
                                                {/* Título */}
                                                <td>
                                                    <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
                                                        <i className="fa-solid fa-file-pdf" style={{ color: "#ef4444", fontSize: "1.1rem" }}></i>
                                                        <div>
                                                            <div style={{ fontWeight: 600, color: "#f8fafc" }}>{doc.title}</div>
                                                            <div style={{ fontSize: "0.72rem", color: "#64748b", fontFamily: "monospace" }}>
                                                                {doc.id.substring(0, 18)}...
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>

                                                {/* Año */}
                                                <td>
                                                    <span style={{ fontWeight: 600, color: "#cbd5e1" }}>
                                                        {doc.year || "N/A"}
                                                    </span>
                                                </td>

                                                {/* Convocatoria */}
                                                <td>
                                                    <span style={{ fontSize: "0.85rem", color: "#94a3b8" }}>
                                                        {doc.exam_type || "Saber 11"}
                                                    </span>
                                                </td>

                                                {/* Respuestas */}
                                                <td>
                                                    {doc.has_answer_key ? (
                                                        <span style={{ color: "#22c55e", fontSize: "0.82rem", display: "inline-flex", alignItems: "center", gap: "5px" }}>
                                                            <i className="fa-solid fa-check"></i> Con Clave
                                                        </span>
                                                    ) : (
                                                        <span style={{ color: "#64748b", fontSize: "0.82rem" }}>Sin Clave</span>
                                                    )}
                                                </td>

                                                {/* Estado */}
                                                <td>
                                                    <span
                                                        className={`qst-doc-badge ${
                                                            doc.status === "uploaded"
                                                                ? "qst-doc-badge--uploaded"
                                                                : doc.status === "processing"
                                                                ? "qst-doc-badge--processing"
                                                                : "qst-doc-badge--failed"
                                                        }`}
                                                    >
                                                        <span className="qst-status-dot"></span>
                                                        {doc.status}
                                                    </span>
                                                </td>

                                                {/* Fecha */}
                                                <td style={{ fontSize: "0.8rem", color: "#94a3b8" }}>
                                                    {doc.created_at
                                                        ? new Date(doc.created_at).toLocaleDateString("es-CO", {
                                                              year: "numeric",
                                                              month: "short",
                                                              day: "numeric",
                                                          })
                                                        : "N/A"}
                                                </td>

                                                {/* Acciones */}
                                                <td>
                                                    <div className="qst-actions-cell" style={{ justifyContent: "center" }}>
                                                        <button
                                                            className="qst-action-btn qst-action-btn--view"
                                                            title="Abrir PDF mediante URL temporal firmada"
                                                            type="button"
                                                            onClick={() => handleDirectOpenPdf(doc)}
                                                        >
                                                            <i className="fa-solid fa-arrow-up-right-from-square"></i>
                                                        </button>
                                                        <button
                                                            className="qst-action-btn qst-action-btn--edit"
                                                            title="Ver detalles y metadatos en Supabase"
                                                            type="button"
                                                            onClick={() => handleOpenDetail(doc)}
                                                        >
                                                            <i className="fa-solid fa-circle-info"></i>
                                                        </button>
                                                        <button
                                                            className="qst-action-btn qst-action-btn--delete"
                                                            title="Eliminar de Storage y Base de Datos"
                                                            type="button"
                                                            onClick={() => handlePromptDelete(doc)}
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
                                                    <i className="fa-solid fa-folder-open"></i>
                                                    <p>No se encontraron cuadernillos en el banco.</p>
                                                    <button
                                                        type="button"
                                                        className="qst-btn-save"
                                                        style={{ marginTop: "10px" }}
                                                        onClick={() => setIsUploadModalOpen(true)}
                                                    >
                                                        <i className="fa-solid fa-file-arrow-up"></i> Cargar el primer cuadernillo PDF
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="qst-table-footer">
                            <span>Mostrando {documents.length} documento(s) registrado(s)</span>
                            <span>Conexión: Supabase Storage S3 (icfes-documents)</span>
                        </div>
                    </section>
                </>
            )}

            {/* =========================================================
                PESTAÑA 1: BANCO DE PREGUNTAS (ESTRUCTURA ORIGINAL INTACTA)
                ========================================================= */}
            {activeTab === "questions" && (
                <>
                    {/* STATS ORIGINALES */}
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

                    {/* BARRA DE HERRAMIENTAS ORIGINAL */}
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
                        </div>
                    </section>

                    {/* TABLA ORIGINAL DE PREGUNTAS */}
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
                                                <td>
                                                    <span className="qst-id-tag">#{q.id}</span>
                                                </td>
                                                <td>
                                                    <div className="qst-subject-tag">
                                                        <span
                                                            className="qst-subject-dot"
                                                            style={{ backgroundColor: q.color }}
                                                        ></span>
                                                        {q.subject}
                                                    </div>
                                                </td>
                                                <td>
                                                    <span className="qst-topic-text">{q.topic}</span>
                                                </td>
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
                                                <td style={{ fontSize: "0.8rem", color: "#94a3b8" }}>
                                                    {q.date}
                                                </td>
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
                </>
            )}

            {/* =========================================================
                PESTAÑA 3: EXTRACCIÓN Y REVISIÓN IA (FASE 3)
                ========================================================= */}
            {activeTab === "ai_extraction" && (
                <div className="qst-ai-preview-card">
                    <div className="qst-ai-preview-icon">
                        <i className="fa-solid fa-robot"></i>
                    </div>
                    <h2 style={{ fontSize: "1.3rem", fontWeight: 700, color: "#ffffff", margin: 0 }}>
                        Pipeline de Extracción Multimodal con Gemini (Fase 3)
                    </h2>
                    <p style={{ maxWidth: "600px", color: "#94a3b8", fontSize: "0.9rem", lineHeight: 1.6, margin: 0 }}>
                        Este módulo procesará los cuadernillos PDF subidos al bucket privado{" "}
                        <code style={{ color: "#22c55e", background: "rgba(34, 197, 94, 0.1)", padding: "2px 6px", borderRadius: "4px" }}>
                            icfes-documents
                        </code>{" "}
                        mediante Gemini 2.5 Flash. Extraerá automáticamente enunciados, opciones A-B-C-D, imágenes asociadas (enviadas a{" "}
                        <code style={{ color: "#38bdf8", background: "rgba(56, 189, 248, 0.1)", padding: "2px 6px", borderRadius: "4px" }}>
                            icfes-media
                        </code>
                        ) y dejará las preguntas en estado borrador para su revisión y publicación controlada.
                    </p>
                    <div style={{ display: "flex", gap: "10px", marginTop: "8px" }}>
                        <button
                            type="button"
                            className="qst-btn-save"
                            onClick={() => setActiveTab("documents")}
                        >
                            <i className="fa-solid fa-file-pdf"></i> Ver Cuadernillos Disponibles
                        </button>
                    </div>
                </div>
            )}

            {/* =========================================================
                MODAL 1: SUBIR CUADERNILLO PDF (NUEVO)
                ========================================================= */}
            {isUploadModalOpen && (
                <div className="qst-modal-overlay" onClick={() => !isUploading && setIsUploadModalOpen(false)}>
                    <div className="qst-modal-card" onClick={(e) => e.stopPropagation()}>
                        <div className="qst-modal-header">
                            <h2 className="qst-modal-title">
                                <i className="fa-solid fa-file-arrow-up"></i>
                                Cargar Cuadernillo ICFES (PDF)
                            </h2>
                            <button
                                className="qst-modal-close-btn"
                                onClick={() => !isUploading && setIsUploadModalOpen(false)}
                                disabled={isUploading}
                                aria-label="Cerrar modal"
                            >
                                <i className="fa-solid fa-xmark"></i>
                            </button>
                        </div>

                        <form onSubmit={handleUploadSubmit}>
                            <div className="qst-modal-body">
                                {/* Zona de Selección de Archivo PDF */}
                                <div className="qst-form-group">
                                    <label className="qst-form-label">Archivo PDF del Cuadernillo *</label>
                                    <div
                                        className="qst-dropzone"
                                        onClick={() => fileInputRef.current?.click()}
                                    >
                                        <i className="fa-solid fa-cloud-arrow-up"></i>
                                        <p className="qst-dropzone-title">
                                            {selectedPdfFile ? "Cambiar archivo seleccionado" : "Haz clic para seleccionar el PDF"}
                                        </p>
                                        <p className="qst-dropzone-subtitle">
                                            Formato .pdf oficial ICFES (máximo 50 MB)
                                        </p>
                                        <input
                                            ref={fileInputRef}
                                            type="file"
                                            accept="application/pdf,.pdf"
                                            style={{ display: "none" }}
                                            onChange={handleFileChange}
                                        />
                                    </div>

                                    {selectedPdfFile && (
                                        <div className="qst-file-pill">
                                            <div className="qst-file-pill-info">
                                                <i className="fa-solid fa-file-pdf"></i>
                                                <div>
                                                    <div className="qst-file-pill-name">{selectedPdfFile.name}</div>
                                                    <div className="qst-file-pill-size">
                                                        {(selectedPdfFile.size / (1024 * 1024)).toFixed(2)} MB • Archivo listo para subida
                                                    </div>
                                                </div>
                                            </div>
                                            {!isUploading && (
                                                <button
                                                    type="button"
                                                    className="qst-file-pill-remove"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        setSelectedPdfFile(null);
                                                        if (fileInputRef.current) fileInputRef.current.value = "";
                                                    }}
                                                    title="Quitar archivo"
                                                >
                                                    <i className="fa-solid fa-trash-can"></i>
                                                </button>
                                            )}
                                        </div>
                                    )}
                                </div>

                                {/* Título del documento */}
                                <div className="qst-form-group">
                                    <label className="qst-form-label">Título descriptivo del Cuadernillo *</label>
                                    <input
                                        type="text"
                                        placeholder="Ej: Cuadernillo Saber 11 - Calendario A 2024 (Sesión 1)"
                                        className="qst-form-input"
                                        value={uploadTitle}
                                        onChange={(e) => setUploadTitle(e.target.value)}
                                        required
                                        disabled={isUploading}
                                    />
                                </div>

                                {/* Año y Convocatoria */}
                                <div className="qst-form-grid-2">
                                    <div className="qst-form-group">
                                        <label className="qst-form-label">Año de la Convocatoria</label>
                                        <input
                                            type="number"
                                            min="1990"
                                            max="2050"
                                            className="qst-form-input"
                                            value={uploadYear}
                                            onChange={(e) => setUploadYear(e.target.value)}
                                            disabled={isUploading}
                                        />
                                    </div>

                                    <div className="qst-form-group">
                                        <label className="qst-form-label">Tipo de Examen</label>
                                        <select
                                            className="qst-form-select"
                                            value={uploadExamType}
                                            onChange={(e) => setUploadExamType(e.target.value)}
                                            disabled={isUploading}
                                        >
                                            <option value="Saber 11">Saber 11</option>
                                            <option value="Saber Pro">Saber Pro</option>
                                            <option value="Simulacro Diagnóstico">Simulacro Diagnóstico</option>
                                            <option value="Prueba Guía ICFES">Prueba Guía ICFES</option>
                                        </select>
                                    </div>
                                </div>

                                {/* Páginas y Hoja de respuestas */}
                                <div className="qst-form-grid-2">
                                    <div className="qst-form-group">
                                        <label className="qst-form-label">Total de Páginas (Opcional)</label>
                                        <input
                                            type="number"
                                            min="1"
                                            placeholder="Ej: 48"
                                            className="qst-form-input"
                                            value={uploadTotalPages}
                                            onChange={(e) => setUploadTotalPages(e.target.value)}
                                            disabled={isUploading}
                                        />
                                    </div>

                                    <div className="qst-form-group" style={{ justifyContent: "center" }}>
                                        <label className="qst-form-label" style={{ marginBottom: "8px" }}>
                                            Hoja de Respuestas
                                        </label>
                                        <label style={{ display: "flex", alignItems: "center", gap: "10px", cursor: "pointer", color: "#f8fafc", fontSize: "0.88rem" }}>
                                            <input
                                                type="checkbox"
                                                checked={uploadHasAnswerKey}
                                                onChange={(e) => setUploadHasAnswerKey(e.target.checked)}
                                                disabled={isUploading}
                                                style={{ width: "18px", height: "18px", accentColor: "#22c55e" }}
                                            />
                                            <span>Incluye hoja de respuestas oficial</span>
                                        </label>
                                    </div>
                                </div>

                                {/* Barra de Progreso de Subida */}
                                {isUploading && (
                                    <div style={{ marginTop: "10px" }}>
                                        <div style={{ display: "flex", justifyContent: "space-between", fontSize: "0.78rem", color: "#94a3b8", marginBottom: "6px" }}>
                                            <span>Subiendo a Supabase Storage (icfes-documents)...</span>
                                            <span>{uploadProgress}%</span>
                                        </div>
                                        <div style={{ width: "100%", height: "6px", background: "rgba(255,255,255,0.08)", borderRadius: "3px", overflow: "hidden" }}>
                                            <div
                                                style={{
                                                    width: `${uploadProgress}%`,
                                                    height: "100%",
                                                    background: "linear-gradient(90deg, #22c55e, #16a34a)",
                                                    transition: "width 0.2s ease",
                                                }}
                                            ></div>
                                        </div>
                                    </div>
                                )}
                            </div>

                            <div className="qst-modal-footer">
                                <button
                                    type="button"
                                    className="qst-btn-cancel"
                                    onClick={() => setIsUploadModalOpen(false)}
                                    disabled={isUploading}
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    className="qst-btn-save"
                                    disabled={isUploading || !selectedPdfFile}
                                    style={{ opacity: isUploading || !selectedPdfFile ? 0.6 : 1 }}
                                >
                                    {isUploading ? (
                                        <>
                                            <span className="qst-spinner" style={{ width: "14px", height: "14px", borderWidth: "2px" }}></span>
                                            <span>Subiendo...</span>
                                        </>
                                    ) : (
                                        <>
                                            <i className="fa-solid fa-cloud-arrow-up"></i>
                                            <span>Subir Documento</span>
                                        </>
                                    )}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* =========================================================
                MODAL 2: DETALLES DE DOCUMENTO (METADATOS & URL FIRMADA)
                ========================================================= */}
            {isDetailModalOpen && selectedDoc && (
                <div className="qst-modal-overlay" onClick={() => setIsDetailModalOpen(false)}>
                    <div className="qst-modal-card" onClick={(e) => e.stopPropagation()}>
                        <div className="qst-modal-header">
                            <h2 className="qst-modal-title">
                                <i className="fa-solid fa-circle-info"></i>
                                Metadatos del Cuadernillo
                            </h2>
                            <button
                                className="qst-modal-close-btn"
                                onClick={() => setIsDetailModalOpen(false)}
                                aria-label="Cerrar modal"
                            >
                                <i className="fa-solid fa-xmark"></i>
                            </button>
                        </div>

                        <div className="qst-modal-body">
                            <div style={{ display: "flex", flexDirection: "column", gap: "14px" }}>
                                <div style={{ background: "rgba(10, 17, 40, 0.6)", padding: "16px", borderRadius: "10px", border: "1px solid var(--qst-border)" }}>
                                    <h3 style={{ fontSize: "1.05rem", fontWeight: 700, color: "#ffffff", margin: "0 0 8px 0" }}>
                                        {selectedDoc.title}
                                    </h3>
                                    <div style={{ display: "grid", gridTemplateColumns: "repeat(2, 1fr)", gap: "10px", fontSize: "0.85rem" }}>
                                        <div>
                                            <span style={{ color: "#64748b" }}>UUID en Base de Datos:</span>
                                            <div style={{ color: "#94a3b8", fontFamily: "monospace", fontSize: "0.75rem", wordBreak: "break-all" }}>
                                                {selectedDoc.id}
                                            </div>
                                        </div>
                                        <div>
                                            <span style={{ color: "#64748b" }}>Año / Convocatoria:</span>
                                            <div style={{ color: "#f8fafc", fontWeight: 600 }}>
                                                {selectedDoc.year || "N/A"} • {selectedDoc.exam_type || "Saber 11"}
                                            </div>
                                        </div>
                                        <div>
                                            <span style={{ color: "#64748b" }}>Estado de Procesamiento:</span>
                                            <div>
                                                <span className="qst-doc-badge qst-doc-badge--uploaded">
                                                    {selectedDoc.status}
                                                </span>
                                            </div>
                                        </div>
                                        <div>
                                            <span style={{ color: "#64748b" }}>Ruta en Storage:</span>
                                            <div style={{ color: "#38bdf8", fontFamily: "monospace", fontSize: "0.75rem", wordBreak: "break-all" }}>
                                                {selectedDoc.storage_path}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {docDetailData?.storage && (
                                    <div style={{ background: "rgba(34, 197, 94, 0.05)", padding: "14px 16px", borderRadius: "10px", border: "1px solid rgba(34, 197, 94, 0.2)" }}>
                                        <div style={{ display: "flex", alignItems: "center", gap: "8px", color: "#22c55e", fontWeight: 600, fontSize: "0.88rem", marginBottom: "8px" }}>
                                            <i className="fa-solid fa-cloud-check"></i>
                                            <span>Verificado en Supabase Storage S3</span>
                                        </div>
                                        <div style={{ display: "flex", gap: "24px", fontSize: "0.82rem", color: "#94a3b8" }}>
                                            <span>Bucket: <strong>{docDetailData.storage.bucket}</strong></span>
                                            <span>Tamaño: <strong>{(docDetailData.storage.size_bytes / 1024).toFixed(1)} KB</strong></span>
                                            <span>Tipo: <strong>{docDetailData.storage.mime_type || "application/pdf"}</strong></span>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="qst-modal-footer">
                            <button
                                type="button"
                                className="qst-btn-cancel"
                                onClick={() => setIsDetailModalOpen(false)}
                            >
                                Cerrar
                            </button>
                            <button
                                type="button"
                                className="qst-btn-save"
                                onClick={() => handleDirectOpenPdf(selectedDoc)}
                            >
                                <i className="fa-solid fa-arrow-up-right-from-square"></i>
                                <span>Abrir PDF Seguro</span>
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* =========================================================
                MODAL 3: CONFIRMAR ELIMINACIÓN DE DOCUMENTO
                ========================================================= */}
            {isDeleteModalOpen && selectedDoc && (
                <div className="qst-modal-overlay" onClick={() => !isDeleting && setIsDeleteModalOpen(false)}>
                    <div className="qst-modal-card" style={{ maxWidth: "460px" }} onClick={(e) => e.stopPropagation()}>
                        <div className="qst-modal-header">
                            <h2 className="qst-modal-title" style={{ color: "#ef4444" }}>
                                <i className="fa-solid fa-triangle-exclamation"></i>
                                Confirmar Eliminación
                            </h2>
                            <button
                                className="qst-modal-close-btn"
                                onClick={() => !isDeleting && setIsDeleteModalOpen(false)}
                                disabled={isDeleting}
                                aria-label="Cerrar modal"
                            >
                                <i className="fa-solid fa-xmark"></i>
                            </button>
                        </div>

                        <div className="qst-modal-body">
                            <p style={{ color: "#f8fafc", fontSize: "0.92rem", margin: "0 0 10px 0" }}>
                                ¿Estás seguro de que deseas eliminar permanentemente el cuadernillo?
                            </p>
                            <div style={{ background: "rgba(10, 17, 40, 0.7)", padding: "12px 14px", borderRadius: "8px", border: "1px solid var(--qst-border)", marginBottom: "12px" }}>
                                <div style={{ fontWeight: 700, color: "#ffffff" }}>{selectedDoc.title}</div>
                                <div style={{ fontSize: "0.75rem", color: "#64748b" }}>ID: {selectedDoc.id}</div>
                            </div>
                            <p style={{ color: "#ef4444", fontSize: "0.82rem", margin: 0 }}>
                                <i className="fa-solid fa-circle-exclamation"></i> Esta acción eliminará el archivo físico en Supabase Storage y su registro en PostgreSQL. No se puede deshacer.
                            </p>
                        </div>

                        <div className="qst-modal-footer">
                            <button
                                type="button"
                                className="qst-btn-cancel"
                                onClick={() => setIsDeleteModalOpen(false)}
                                disabled={isDeleting}
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                className="qst-btn-save"
                                style={{ background: "linear-gradient(135deg, #ef4444 0%, #dc2626 100%)", boxShadow: "0 4px 12px rgba(239, 68, 68, 0.3)" }}
                                onClick={handleConfirmDelete}
                                disabled={isDeleting}
                            >
                                {isDeleting ? (
                                    <>
                                        <span className="qst-spinner" style={{ width: "14px", height: "14px" }}></span>
                                        <span>Eliminando...</span>
                                    </>
                                ) : (
                                    <>
                                        <i className="fa-solid fa-trash-can"></i>
                                        <span>Confirmar Eliminación</span>
                                    </>
                                )}
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* =========================================================
                MODAL 4: CREACIÓN MANUAL DE PREGUNTAS (EXISTENTE)
                ========================================================= */}
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
                                <div className="qst-form-grid-3">
                                    <div className="qst-form-group">
                                        <label className="qst-form-label">Materia</label>
                                        <select
                                            className="qst-form-select"
                                            value={formData.subject}
                                            onChange={(e) => setFormData({ ...formData, subject: e.target.value })}
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
                                            onChange={(e) => setFormData({ ...formData, topic: e.target.value })}
                                            required
                                        />
                                    </div>

                                    <div className="qst-form-group">
                                        <label className="qst-form-label">Dificultad</label>
                                        <select
                                            className="qst-form-select"
                                            value={formData.difficulty}
                                            onChange={(e) => setFormData({ ...formData, difficulty: e.target.value })}
                                        >
                                            <option value="Fácil">Fácil</option>
                                            <option value="Media">Media</option>
                                            <option value="Difícil">Difícil</option>
                                        </select>
                                    </div>
                                </div>

                                <div className="qst-form-group">
                                    <label className="qst-form-label">Enunciado de la Pregunta</label>
                                    <textarea
                                        placeholder="Redacta el texto, contexto o situación problema del reactivo ICFES..."
                                        className="qst-form-textarea"
                                        rows="3"
                                        value={formData.statement}
                                        onChange={(e) => setFormData({ ...formData, statement: e.target.value })}
                                        required
                                    ></textarea>
                                </div>

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
                                                onChange={(e) => setFormData({ ...formData, optionA: e.target.value })}
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
                                                onChange={(e) => setFormData({ ...formData, optionB: e.target.value })}
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
                                                onChange={(e) => setFormData({ ...formData, optionC: e.target.value })}
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
                                                onChange={(e) => setFormData({ ...formData, optionD: e.target.value })}
                                                required
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div className="qst-form-grid-2">
                                    <div className="qst-form-group">
                                        <label className="qst-form-label">Respuesta Correcta</label>
                                        <select
                                            className="qst-form-select"
                                            value={formData.correctOption}
                                            onChange={(e) => setFormData({ ...formData, correctOption: e.target.value })}
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
                                            onChange={(e) => setFormData({ ...formData, explanation: e.target.value })}
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
