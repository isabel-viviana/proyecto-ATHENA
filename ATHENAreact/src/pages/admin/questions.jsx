import { useState, useMemo, useEffect, useCallback, useRef } from "react";
import "./questions.css";
import {
    getDocuments,
    uploadDocument,
    getDocument,
    deleteDocument,
    extractDocumentQuestions,
    getExtractionStatus,
    getIqpeStatus,
    retryDocumentProcessing,
    getExtractedQuestions,
    getQuestion,
    updateQuestion,
    approveQuestion,
    rejectQuestion,
    getSubjectsCatalog,
    getTopicsCatalog,
    getQuestions,
    getDocumentMedia,
    bulkApproveQuestions,
    getDocumentAudit,
    runDocumentAudit,
} from "../../api/documents";
import { MediaImage, StimulusMedia, QuestionMedia, MediaAssociationPanel } from "../../components/admin/QuestionMedia";

// Mapeo de áreas académicas oficiales del ICFES
const SUBJECT_MAP = {
    1: { name: "Lectura Crítica", color: "#3b82f6", bg: "rgba(59, 130, 246, 0.12)", border: "rgba(59, 130, 246, 0.3)", icon: "fa-book-open" },
    2: { name: "Matemáticas", color: "#10b981", bg: "rgba(16, 185, 129, 0.12)", border: "rgba(16, 185, 129, 0.3)", icon: "fa-calculator" },
    3: { name: "Ciencias Naturales", color: "#a855f7", bg: "rgba(168, 85, 247, 0.12)", border: "rgba(168, 85, 247, 0.3)", icon: "fa-atom" },
    4: { name: "Sociales y Ciudadanas", color: "#f59e0b", bg: "rgba(245, 158, 11, 0.12)", border: "rgba(245, 158, 11, 0.3)", icon: "fa-landmark" },
    5: { name: "Inglés", color: "#06b6d4", bg: "rgba(6, 182, 212, 0.12)", border: "rgba(6, 182, 212, 0.3)", icon: "fa-language" },
};

// Componente para renderizar cada pregunta extraída como unidad completa pedagógica
const QuestionCardItem = ({
    q,
    isExpanded,
    onToggleExpand,
    onEdit,
    onApprove,
    onReject,
    isProcessingAction,
    isSelected = false,
    onToggleSelect = null,
    showCheckbox = false,
}) => {
    const subjectInfo = SUBJECT_MAP[q.subject_id] || {
        name: "ICFES General",
        color: "#94a3b8",
        bg: "rgba(148, 163, 184, 0.12)",
        border: "rgba(148, 163, 184, 0.3)",
        icon: "fa-graduation-cap",
    };

    const isVerifiedKey = q.key_status === "verified";
    const isPublished = q.status === "published";
    const isApproved = q.status === "review";
    const isArchived = q.status === "archived";
    const sourcePage = q.source_page || q.stimulus?.source_page || null;
    const hasConfirmedMedia =
        (q.confirmed_media_assets && q.confirmed_media_assets.length > 0) ||
        Boolean(q.question_image_path) ||
        (q.stimulus?.confirmed_media_assets && q.stimulus.confirmed_media_assets.length > 0) ||
        Boolean(q.stimulus?.media_path);

    return (
        <article
            className={`qst-question-item-card ${isSelected ? "qst-question-item-card--selected" : ""}`}
            style={{
                border: isSelected ? "2px solid #22c55e" : undefined,
                background: isSelected ? "rgba(34, 197, 94, 0.04)" : undefined,
                transition: "all 0.2s ease",
            }}
        >
            {/* Header de la Pregunta */}
            <div className="qst-question-card-top">
                <div className="qst-question-meta-left" style={{ display: "flex", alignItems: "center", gap: 8, flexWrap: "wrap" }}>
                    {showCheckbox && (
                        <label
                            style={{
                                display: "inline-flex",
                                alignItems: "center",
                                gap: 6,
                                cursor: "pointer",
                                padding: "3px 8px",
                                borderRadius: 6,
                                background: isSelected ? "rgba(34, 197, 94, 0.18)" : "rgba(148, 163, 184, 0.1)",
                                border: isSelected ? "1px solid #4ade80" : "1px solid rgba(148, 163, 184, 0.25)",
                                userSelect: "none",
                            }}
                            title="Seleccionar para aprobación masiva"
                            onClick={(e) => e.stopPropagation()}
                        >
                            <input
                                type="checkbox"
                                checked={isSelected}
                                onChange={() => onToggleSelect && onToggleSelect(q.id)}
                                style={{ width: 16, height: 16, cursor: "pointer", accentColor: "#22c55e" }}
                            />
                            <span style={{ fontSize: "0.72rem", fontWeight: 700, color: isSelected ? "#4ade80" : "#94a3b8" }}>
                                {isSelected ? "Seleccionada" : "Seleccionar"}
                            </span>
                        </label>
                    )}

                    <span className="qst-question-num-tag">
                        Pregunta #{q.order_in_group || (q.id ? q.id.slice(0, 8) : "—")}
                    </span>

                    {sourcePage && (
                        <span
                            style={{
                                display: "inline-flex",
                                alignItems: "center",
                                gap: "4px",
                                fontSize: "0.74rem",
                                fontWeight: 600,
                                color: "#94a3b8",
                                background: "rgba(148, 163, 184, 0.12)",
                                border: "1px solid rgba(148, 163, 184, 0.25)",
                                padding: "2px 8px",
                                borderRadius: "6px",
                            }}
                            title={`Página ${sourcePage} del cuadernillo original`}
                        >
                            <i className="fa-solid fa-file-lines" style={{ color: "#38bdf8" }}></i>
                            Pág. {sourcePage}
                        </span>
                    )}

                    <span
                        className="qst-subject-badge"
                        style={{
                            color: subjectInfo.color,
                            backgroundColor: subjectInfo.bg,
                            border: `1px solid ${subjectInfo.border}`,
                        }}
                    >
                        <i className={`fa-solid ${subjectInfo.icon}`}></i>
                        {subjectInfo.name}
                    </span>

                    {isPublished ? (
                        <span
                            className="qst-status-pill-published"
                            style={{
                                display: "inline-flex",
                                alignItems: "center",
                                gap: "5px",
                                padding: "3px 9px",
                                borderRadius: "999px",
                                fontSize: "0.74rem",
                                fontWeight: 700,
                                background: "rgba(168, 85, 247, 0.15)",
                                color: "#c084fc",
                                border: "1px solid rgba(168, 85, 247, 0.35)",
                            }}
                        >
                            <i className="fa-solid fa-globe"></i> Publicada
                        </span>
                    ) : isApproved ? (
                        <span className="qst-status-pill-review">
                            <i className="fa-solid fa-circle-check"></i> Aprobada / Revisada
                        </span>
                    ) : isArchived ? (
                        <span className="qst-status-pill-archived">
                            <i className="fa-solid fa-ban"></i> Rechazada / Archivada
                        </span>
                    ) : (
                        <span className="qst-status-pill-draft">
                            <i className="fa-solid fa-clock-rotate-left"></i> Borrador IA
                        </span>
                    )}

                    {hasConfirmedMedia && (
                        <span
                            style={{
                                display: "inline-flex",
                                alignItems: "center",
                                gap: "5px",
                                padding: "3px 9px",
                                borderRadius: "999px",
                                fontSize: "0.74rem",
                                fontWeight: 700,
                                background: "rgba(56, 189, 248, 0.14)",
                                color: "#38bdf8",
                                border: "1px solid rgba(56, 189, 248, 0.35)",
                            }}
                            title="Cuenta con recurso visual confirmado"
                        >
                            <i className="fa-solid fa-image"></i> Recurso Visual
                        </span>
                    )}
                </div>

                <div className="qst-question-meta-right">
                    {/* Badge de Estado de Auditoría de Calidad */}
                    {q.extraction_warnings ? (
                        <span className="qst-audit-badge-review" title={`Observación de auditoría: ${q.extraction_warnings}`}>
                            <i className="fa-solid fa-triangle-exclamation"></i>
                            <span>Con Observación</span>
                        </span>
                    ) : (
                        <span className="qst-audit-badge-ready" title="Validación automática completada sin observaciones">
                            <i className="fa-solid fa-circle-check"></i>
                            <span>Lista para Aprobación</span>
                        </span>
                    )}

                    {/* Badge de Verificación de Clave */}
                    {isVerifiedKey ? (
                        <span className="qst-key-badge qst-key-badge--verified" title="Clave de respuesta verificada en hoja oficial o calibrada por evaluador">
                            <i className="fa-solid fa-circle-check"></i>
                            <span>Clave Verificada</span>
                        </span>
                    ) : (
                        <span className="qst-key-badge qst-key-badge--unverified" title="Clave pedagógica pendiente de validación (aprobación permitida)">
                            <i className="fa-solid fa-triangle-exclamation"></i>
                            <span>Clave no verificada</span>
                        </span>
                    )}

                    <button
                        type="button"
                        className="qst-btn-toggle-options"
                        onClick={onToggleExpand}
                        title={isExpanded ? "Ocultar opciones" : "Ver opciones"}
                        aria-expanded={isExpanded}
                    >
                        <i className={isExpanded ? "fa-solid fa-chevron-up" : "fa-solid fa-chevron-down"}></i>
                    </button>
                </div>
            </div>

            {/* Enunciado */}
            <div className="qst-question-statement-text">
                {q.statement}
            </div>
            <QuestionMedia question={q} />

            {/* Observación de Auditoría de Calidad si existe */}
            {q.extraction_warnings && (
                <div className="qst-audit-warning-box">
                    <i className="fa-solid fa-triangle-exclamation" style={{ marginTop: "2px", flexShrink: 0 }}></i>
                    <div>
                        <strong>Observación de Auditoría de Calidad:</strong> {q.extraction_warnings}
                    </div>
                </div>
            )}

            {/* Opciones de Respuesta */}
            {isExpanded && (
                <div className="qst-options-container-box">
                    {!isVerifiedKey && (
                        <div className="qst-unverified-warning">
                            <i className="fa-solid fa-circle-info"></i>
                            <span>
                                <strong>Clave no verificada en documento fuente:</strong> Las opciones se presentan sin marcar respuesta correcta para evitar sesgos y permitir la calibración pedagógica docente.
                            </span>
                        </div>
                    )}

                    <div className="qst-options-list-grid">
                        {q.options && q.options.length > 0 ? (
                            q.options.map((opt) => {
                                const isCorrectVerified = isVerifiedKey && opt.is_correct === true;
                                return (
                                    <div
                                        key={opt.id || opt.option_letter}
                                        className={`qst-option-row ${
                                            isCorrectVerified ? "qst-option-row--correct" : "qst-option-row--neutral"
                                        }`}
                                    >
                                        <div
                                            className={`qst-option-letter-badge ${
                                                isCorrectVerified
                                                    ? "qst-option-letter-badge--correct"
                                                    : "qst-option-letter-badge--neutral"
                                            }`}
                                        >
                                            {opt.option_letter}
                                        </div>
                                        <div className="qst-option-content">
                                            <span className="qst-option-text-label">
                                                {opt.option_text}
                                            </span>
                                            {opt.option_image_path && (
                                                <MediaImage path={opt.option_image_path} alt={`Imagen de la opción ${opt.option_letter}`} maxHeight={180} />
                                            )}
                                            {isCorrectVerified && (
                                                <span className="qst-correct-pill-label">
                                                    <i className="fa-solid fa-check-double"></i>
                                                    Correcta Verificada
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                );
                            })
                        ) : (
                            <div style={{ color: "#94a3b8", fontSize: "0.82rem", fontStyle: "italic" }}>
                                No se encontraron opciones registradas para este reactivo.
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* Explicación / Justificación Pedagógica si existe */}
            {q.explanation && isExpanded && (
                <div style={{ background: "rgba(56, 189, 248, 0.05)", border: "1px dashed rgba(56, 189, 248, 0.25)", padding: "10px 14px", borderRadius: "8px", fontSize: "0.82rem", color: "#93c5fd" }}>
                    <i className="fa-solid fa-lightbulb" style={{ marginRight: "6px", color: "#38bdf8" }}></i>
                    <strong>Justificación pedagógica:</strong> {q.explanation}
                </div>
            )}

            {/* Barra de Acciones de Revisión Pedagógica (Fase 2.9) */}
            <div className="qst-question-actions-bar">
                <div className="qst-actions-status-group">
                    <span style={{ fontSize: "0.78rem", color: "#64748b" }}>
                        ID: <span style={{ fontFamily: "monospace", color: "#94a3b8" }}>{q.id ? q.id.slice(0, 13) : "—"}...</span>
                    </span>
                    {q.topic?.name && (
                        <span style={{ fontSize: "0.75rem", color: "#a855f7", background: "rgba(168, 85, 247, 0.1)", padding: "2px 8px", borderRadius: "4px" }}>
                            <i className="fa-solid fa-tag"></i> {q.topic.name}
                        </span>
                    )}
                </div>

                <div className="qst-actions-buttons-group">
                    <button
                        type="button"
                        className="qst-btn-action-edit"
                        onClick={() => onEdit(q)}
                        disabled={isProcessingAction}
                        title="Editar enunciado, opciones, materia y clave"
                    >
                        <i className="fa-solid fa-pen-to-square"></i>
                        <span>Editar pregunta</span>
                    </button>

                    <button
                        type="button"
                        className="qst-btn-action-approve"
                        onClick={() => onApprove(q)}
                        disabled={isProcessingAction || isApproved}
                        title={
                            isApproved
                                ? "Pregunta ya aprobada para el banco"
                                : "Aprobar pedagógicamente para el banco oficial"
                        }
                    >
                        {isProcessingAction ? (
                            <span className="qst-spinner" style={{ width: "12px", height: "12px", borderWidth: "2px" }}></span>
                        ) : (
                            <i className="fa-solid fa-check"></i>
                        )}
                        <span>{isApproved ? "Aprobada" : "Aprobar"}</span>
                    </button>

                    <button
                        type="button"
                        className="qst-btn-action-reject"
                        onClick={() => onReject(q)}
                        disabled={isProcessingAction || isArchived}
                        title="Rechazar y archivar reactivo sin afectar estímulo"
                    >
                        <i className="fa-solid fa-ban"></i>
                        <span>{isArchived ? "Archivada" : "Rechazar"}</span>
                    </button>
                </div>
            </div>
        </article>
    );
};

const Questions = () => {
    // =========================================================
    // 1. ESTADO DE PESTAÑAS (TABS)
    // =========================================================
    const [activeTab, setActiveTab] = useState("questions"); // "questions", "documents", "ai_extraction"

    // =========================================================
    // 2. ESTADOS: BANCO DE PREGUNTAS REAL (TAB 1)
    // =========================================================
    const [bankQuestions, setBankQuestions] = useState([]);
    const [isLoadingBank, setIsLoadingBank] = useState(false);
    const [bankError, setBankError] = useState(null);
    const [bankStats, setBankStats] = useState({
        total_bank: 0,
        published_count: 0,
        review_count: 0,
        draft_count: 0,
        archived_count: 0,
        verified_count: 0,
    });
    const [bankPagination, setBankPagination] = useState({
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 0,
        from: null,
        to: null,
    });
    const [bankPage, setBankPage] = useState(1);
    const [bankPerPage, setBankPerPage] = useState(10);
    const [bankSearch, setBankSearch] = useState("");
    const [bankSubjectFilter, setBankSubjectFilter] = useState("Todas");
    const [bankStatusFilter, setBankStatusFilter] = useState("official");
    const [expandedBankIds, setExpandedBankIds] = useState(new Set());
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

    const toggleBankExpand = (qId) => {
        setExpandedBankIds((prev) => {
            const next = new Set(prev);
            if (next.has(qId)) {
                next.delete(qId);
            } else {
                next.add(qId);
            }
            return next;
        });
    };

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

    // =========================================================
    // 3.1 ESTADOS: EXTRACCIÓN Y REVISIÓN IA (TAB 3)
    // =========================================================
    const [selectedExtractionDocId, setSelectedExtractionDocId] = useState("");
    const [extractionStatusData, setExtractionStatusData] = useState(null);
    const [iqpeJobData, setIqpeJobData] = useState(null);
    const [extractedQuestions, setExtractedQuestions] = useState([]);
    const [isExtracting, setIsExtracting] = useState(false);
    const [isLoadingExtractionData, setIsLoadingExtractionData] = useState(false);
    const [extractionError, setExtractionError] = useState(null);
    const [extractionSuccess, setExtractionSuccess] = useState(null);
    const [expandedQuestionIds, setExpandedQuestionIds] = useState(new Set());

    // =========================================================
    // 3.2 ESTADOS: REVISIÓN Y EDICIÓN PEDAGÓGICA (FASE 2.9)
    // =========================================================
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [editingQuestion, setEditingQuestion] = useState(null);
    const [editForm, setEditForm] = useState({
        statement: "",
        explanation: "",
        subject_id: 1,
        topic_id: "",
        difficulty: "medium",
        key_status: "unverified",
        correct_option: "A",
        options: {
            A: "",
            B: "",
            C: "",
            D: "",
        },
    });
    const [availableTopics, setAvailableTopics] = useState([]);
    const [isLoadingTopics, setIsLoadingTopics] = useState(false);
    const [isSavingEdit, setIsSavingEdit] = useState(false);
    const [actionLoadingId, setActionLoadingId] = useState(null);
    const [isConfirmRejectOpen, setIsConfirmRejectOpen] = useState(false);
    const [questionToReject, setQuestionToReject] = useState(null);

    // =========================================================
    // 3.3 ESTADOS: SELECCIÓN MÚLTIPLE Y APROBACIÓN MASIVA
    // =========================================================
    const [selectedQuestionIds, setSelectedQuestionIds] = useState(new Set());
    const [isBulkApproving, setIsBulkApproving] = useState(false);
    const [bulkSummary, setBulkSummary] = useState(null);
    const [isBulkSummaryOpen, setIsBulkSummaryOpen] = useState(false);

    // =========================================================
    // 3.4 ESTADOS: AUDITORÍA AUTOMÁTICA Y FILTROS DE CALIDAD
    // =========================================================
    const [auditFilter, setAuditFilter] = useState("all"); // 'all' | 'ready' | 'issues'
    const [isRunningAudit, setIsRunningAudit] = useState(false);


    const fileInputRef = useRef(null);

    // Recursos visuales extraídos del PDF (pendientes y confirmados en media_assets)
    const [docMedia, setDocMedia] = useState(null);
    const fetchDocMedia = useCallback(async (docId) => {
        if (!docId) {
            setDocMedia(null);
            return;
        }
        try {
            const res = await getDocumentMedia(docId);
            setDocMedia(res?.success ? res.data : null);
        } catch {
            setDocMedia(null);
        }
    }, []);

    useEffect(() => {
        if (activeTab === "ai_extraction" && selectedExtractionDocId) {
            fetchDocMedia(selectedExtractionDocId);
        }
    }, [activeTab, selectedExtractionDocId, fetchDocMedia]);

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
    // 4.1 CARGA DEL BANCO DE PREGUNTAS REAL DESDE LARAVEL
    // =========================================================
    const fetchBankQuestions = useCallback(async (targetPage = 1, customPerPage = null) => {
        setIsLoadingBank(true);
        setBankError(null);
        try {
            const page = targetPage || 1;
            const perPageToUse = customPerPage || bankPerPage || 10;
            const params = {
                page,
                per_page: perPageToUse,
                sort: "order_asc",
            };
            if (bankSearch.trim()) params.search = bankSearch.trim();
            if (bankSubjectFilter !== "Todas") params.subject_id = bankSubjectFilter;
            if (bankStatusFilter) params.status = bankStatusFilter;

            const res = await getQuestions(params);
            if (res && res.success) {
                const items = res.data || [];
                setBankQuestions(items);
                // Expandir las opciones de la página actual por defecto para facilitar su lectura
                setExpandedBankIds(new Set(items.map((item) => item.id)));
                if (res.meta) {
                    setBankPagination(res.meta);
                    setBankPage(res.meta.current_page || page);
                }
                if (res.stats) {
                    setBankStats(res.stats);
                }
            } else {
                setBankQuestions([]);
            }
        } catch (err) {
            console.error("Error al cargar banco de preguntas:", err);
            setBankError(
                err.response?.data?.message ||
                "No fue posible conectar con el microservicio Question Bank (puerto 8001). Verifique que el servicio esté activo."
            );
            setBankQuestions([]);
        } finally {
            setIsLoadingBank(false);
        }
    }, [bankSearch, bankSubjectFilter, bankStatusFilter, bankPerPage]);

    useEffect(() => {
        if (activeTab === "questions") {
            fetchBankQuestions(1);
        }
    }, [activeTab, bankSubjectFilter, bankStatusFilter, bankPerPage, fetchBankQuestions]);

    // Agrupa preguntas consecutivas del banco que comparten el mismo estímulo respetando el orden 1 → 49
    const groupedBankBlocks = useMemo(() => {
        if (!bankQuestions || bankQuestions.length === 0) return [];
        const blocks = [];
        bankQuestions.forEach((q) => {
            if (q.stimulus_id && q.stimulus) {
                const lastBlock = blocks[blocks.length - 1];
                if (lastBlock && lastBlock.type === "stimulus" && lastBlock.stimulus.id === q.stimulus_id) {
                    lastBlock.questions.push(q);
                } else {
                    blocks.push({
                        type: "stimulus",
                        stimulus: q.stimulus,
                        questions: [q],
                    });
                }
            } else {
                blocks.push({
                    type: "standalone",
                    question: q,
                });
            }
        });
        return blocks;
    }, [bankQuestions]);

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

            const res = await uploadDocument(payload, (progressEvent) => {
                if (progressEvent.total) {
                    const pct = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                    setUploadProgress(pct);
                }
            });

            setDocSuccess("¡Cuadernillo PDF subido exitosamente! Trabajo IQPE encolado en segundo plano.");
            setIsUploadModalOpen(false);

            // Reset form
            setUploadTitle("");
            setSelectedPdfFile(null);
            setUploadTotalPages("");
            if (fileInputRef.current) fileInputRef.current.value = "";

            // Refrescar lista y pasar a la pestaña de extracción automáticamente
            await fetchDocuments();
            const newDocId = res?.data?.id || res?.data?.document_id;
            if (newDocId) {
                setSelectedExtractionDocId(newDocId);
                setActiveTab("ai_extraction");
                setIsExtracting(true);
                if (res?.data) {
                    setIqpeJobData(res.data);
                }
            }
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

    // =========================================================
    // 8.1 MANEJADORES: EXTRACCIÓN Y REVISIÓN IA CON GEMINI (TAB 3)
    // =========================================================
    const toggleQuestionExpanded = (qId) => {
        setExpandedQuestionIds((prev) => {
            const next = new Set(prev);
            if (next.has(qId)) {
                next.delete(qId);
            } else {
                next.add(qId);
            }
            return next;
        });
    };

    const expandAllQuestions = () => {
        setExpandedQuestionIds(new Set(extractedQuestions.map((q) => q.id)));
    };

    const collapseAllQuestions = () => {
        setExpandedQuestionIds(new Set());
    };

    const loadExtractionData = useCallback(async (docId) => {
        if (!docId) return;
        setIsLoadingExtractionData(true);
        setExtractionError(null);

        try {
            const [statusRes, questionsRes] = await Promise.allSettled([
                getIqpeStatus(docId),
                getExtractedQuestions(docId),
            ]);

            if (statusRes.status === "fulfilled" && statusRes.value?.success) {
                const statusPayload = statusRes.value.data;
                setExtractionStatusData(statusPayload);
                setIqpeJobData(statusPayload);

                if (["queued", "extracting", "solving_round_1", "solving_round_2"].includes(statusPayload?.status)) {
                    setIsExtracting(true);
                } else if (statusPayload?.status === "completed" || statusPayload?.status === "failed") {
                    setIsExtracting(false);
                }
            } else if (statusRes.status === "rejected") {
                console.error("Error al consultar estado IQPE:", statusRes.reason);
            }

            if (questionsRes.status === "fulfilled" && questionsRes.value?.success) {
                const list = questionsRes.value.data?.questions || [];
                setExtractedQuestions(list);
                // Expandir todas las preguntas por defecto para facilitar revisión
                setExpandedQuestionIds(new Set(list.map((q) => q.id)));
            } else if (questionsRes.status === "rejected") {
                setExtractedQuestions([]);
                setExpandedQuestionIds(new Set());
            }
        } catch (err) {
            console.error("Error cargando datos de extracción:", err);
            setExtractionError("No se pudieron cargar los datos de extracción para el documento seleccionado.");
        } finally {
            setIsLoadingExtractionData(false);
        }
    }, []);

    // Polling reactivo para jobs encolados o en extracción en segundo plano
    useEffect(() => {
        if (!selectedExtractionDocId || !isExtracting) return;

        const intervalId = setInterval(async () => {
            try {
                const res = await getIqpeStatus(selectedExtractionDocId);
                if (res && res.success && res.data) {
                    setIqpeJobData(res.data);
                    setExtractionStatusData(res.data);

                    if (res.data.status === "completed") {
                        setIsExtracting(false);
                        setExtractionSuccess(res.data.progress || `¡Extracción completada! ${res.data.total_questions_detected} preguntas detectadas.`);
                        await loadExtractionData(selectedExtractionDocId);
                        await fetchDocuments();
                    } else if (res.data.status === "failed") {
                        setIsExtracting(false);
                        setExtractionError(res.data.error_detail || "El procesamiento asíncrono IQPE ha fallado.");
                        await loadExtractionData(selectedExtractionDocId);
                        await fetchDocuments();
                    }
                }
            } catch (err) {
                console.error("Error en polling de estado IQPE:", err);
            }
        }, 3000);

        return () => clearInterval(intervalId);
    }, [selectedExtractionDocId, isExtracting, loadExtractionData, fetchDocuments]);

    const handleTriggerExtraction = async (docIdToUse = null) => {
        const targetDocId = (typeof docIdToUse === "string" && docIdToUse) ? docIdToUse : selectedExtractionDocId;
        if (!targetDocId || isExtracting) return;
        setIsExtracting(true);
        setExtractionError(null);
        setExtractionSuccess(null);

        try {
            const res = await extractDocumentQuestions(targetDocId);
            if (res?.success) {
                setExtractionSuccess(res.message || "Trabajo IQPE encolado en segundo plano. Monitoreando ejecución...");
                if (res.data) {
                    setIqpeJobData(res.data);
                }
                await fetchDocuments();
            } else {
                setExtractionError(res?.message || "Ocurrió un problema al encolar la extracción.");
                setIsExtracting(false);
            }
        } catch (err) {
            console.error("Error al extraer preguntas:", err);
            const msg = err.response?.data?.message || err.message || "Fallo en la comunicación con el microservicio.";
            setExtractionError(msg);
            setIsExtracting(false);
        }
    };

    const handleRetryProcessing = async () => {
        if (!selectedExtractionDocId || isExtracting) return;
        setIsExtracting(true);
        setExtractionError(null);
        setExtractionSuccess(null);

        try {
            const res = await retryDocumentProcessing(selectedExtractionDocId);
            if (res?.success) {
                setExtractionSuccess(res.message || "Reintento IQPE encolado en segundo plano. Monitoreando ejecución...");
                if (res.data) {
                    setIqpeJobData(res.data);
                }
                await fetchDocuments();
            } else {
                setExtractionError(res?.message || "No se pudo reintentar el procesamiento.");
                setIsExtracting(false);
            }
        } catch (err) {
            console.error("Error al reintentar procesamiento:", err);
            const msg = err.response?.data?.message || err.message || "Error al solicitar reintento.";
            setExtractionError(msg);
            setIsExtracting(false);
        }
    };

    const handleRunAudit = async () => {
        if (!selectedExtractionDocId || isRunningAudit) return;
        setIsRunningAudit(true);
        setExtractionError(null);
        setExtractionSuccess(null);
        try {
            const res = await runDocumentAudit(selectedExtractionDocId);
            if (res && res.success) {
                setExtractionSuccess(res.message || "Auditoría de calidad ejecutada y actualizada correctamente.");
                await loadExtractionData(selectedExtractionDocId);
            } else {
                setExtractionError(res?.message || "No se pudo completar la auditoría de calidad.");
            }
        } catch (err) {
            console.error("Error al ejecutar auditoría:", err);
            setExtractionError(err.response?.data?.message || "Fallo al ejecutar la auditoría de calidad.");
        } finally {
            setIsRunningAudit(false);
        }
    };

    const handleSelectOnlyReady = () => {
        const readyIds = new Set(
            extractedQuestions
                .filter((q) => !q.extraction_warnings && q.status !== "rejected")
                .map((q) => q.id)
        );
        setSelectedQuestionIds(readyIds);
    };

    const handleGoToExtraction = (doc) => {
        setSelectedExtractionDocId(doc.id);
        setActiveTab("ai_extraction");
    };

    // =========================================================
    // 8.2 MANEJADORES: REVISIÓN Y EDICIÓN PEDAGÓGICA (FASE 2.9)
    // =========================================================
    const handleOpenEdit = async (q) => {
        setEditingQuestion(q);
        setExtractionError(null);
        setExtractionSuccess(null);

        let correctLetter = "A";
        const isKeyVerified = q.key_status === "verified";

        const optionsObj = { A: "", B: "", C: "", D: "" };
        if (q.options && q.options.length > 0) {
            q.options.forEach((opt) => {
                const letter = opt.option_letter ? opt.option_letter.toUpperCase() : "A";
                optionsObj[letter] = opt.option_text || "";
                if (opt.is_correct) {
                    correctLetter = letter;
                }
            });
        }

        setEditForm({
            statement: q.statement || "",
            explanation: q.explanation || "",
            subject_id: q.subject_id || 1,
            topic_id: q.topic_id || "",
            difficulty: q.difficulty || "medium",
            key_status: isKeyVerified ? "verified" : "unverified",
            correct_option: correctLetter,
            options: optionsObj,
        });

        setIsEditModalOpen(true);

        // Consultar temas de catálogo para esta materia
        setIsLoadingTopics(true);
        try {
            const res = await getTopicsCatalog(q.subject_id || 1);
            setAvailableTopics(res?.data || []);
        } catch {
            setAvailableTopics([]);
        } finally {
            setIsLoadingTopics(false);
        }
    };

    const handleEditSubjectChange = async (newSubjectId) => {
        const sid = Number(newSubjectId);
        setEditForm((prev) => ({ ...prev, subject_id: sid, topic_id: "" }));
        setIsLoadingTopics(true);
        try {
            const res = await getTopicsCatalog(sid);
            setAvailableTopics(res?.data || []);
        } catch {
            setAvailableTopics([]);
        } finally {
            setIsLoadingTopics(false);
        }
    };

    const handleSaveEdit = async (e) => {
        e.preventDefault();
        if (!editingQuestion) return;

        setIsSavingEdit(true);
        setExtractionError(null);

        try {
            const optionsArray = ["A", "B", "C", "D"].map((letter) => ({
                option_letter: letter,
                option_text: editForm.options[letter] || "",
                is_correct: editForm.key_status === "verified" && editForm.correct_option === letter,
            }));

            const payload = {
                statement: editForm.statement,
                explanation: editForm.explanation,
                subject_id: Number(editForm.subject_id),
                topic_id: editForm.topic_id ? Number(editForm.topic_id) : null,
                difficulty: editForm.difficulty,
                key_status: editForm.key_status,
                correct_option: editForm.correct_option,
                options: optionsArray,
            };

            const res = await updateQuestion(editingQuestion.id, payload);
            if (res && res.success) {
                setExtractionSuccess(`Pregunta #${editingQuestion.order_in_group || editingQuestion.id.slice(0, 8)} actualizada exitosamente.`);
                setIsEditModalOpen(false);
                setEditingQuestion(null);
                // Actualizar estado reactivo local manteniendo la estructura agrupada
                setExtractedQuestions((prev) =>
                    prev.map((item) => (item.id === editingQuestion.id ? { ...item, ...res.data } : item))
                );
                setBankQuestions((prev) =>
                    prev.map((item) => (item.id === editingQuestion.id ? { ...item, ...res.data } : item))
                );
                fetchBankQuestions();
            } else {
                setExtractionError(res?.message || "No se pudo actualizar la pregunta.");
            }
        } catch (err) {
            console.error("Error al actualizar pregunta:", err);
            const msg = err.response?.data?.message || err.message || "Error al actualizar la pregunta.";
            setExtractionError(msg);
        } finally {
            setIsSavingEdit(false);
        }
    };

    const handleApprove = async (q) => {
        setActionLoadingId(q.id);
        setExtractionError(null);
        setExtractionSuccess(null);

        try {
            const res = await approveQuestion(q.id);
            if (res && res.success) {
                setExtractionSuccess(`¡Pregunta #${q.order_in_group || q.id.slice(0, 8)} aprobada pedagógicamente para el banco oficial!`);
                setExtractedQuestions((prev) =>
                    prev.map((item) => (item.id === q.id ? { ...item, ...res.data } : item))
                );
                setBankQuestions((prev) =>
                    prev.map((item) => (item.id === q.id ? { ...item, ...res.data } : item))
                );
                fetchBankQuestions();
            } else {
                setExtractionError(res?.message || "No fue posible aprobar la pregunta.");
            }
        } catch (err) {
            console.error("Error al aprobar pregunta:", err);
            const msg = err.response?.data?.message || err.message || "Fallo al aprobar la pregunta.";
            setExtractionError(msg);
        } finally {
            setActionLoadingId(null);
        }
    };

    // Controladores de Selección Múltiple y Aprobación Masiva
    const handleToggleSelectQuestion = (id) => {
        setSelectedQuestionIds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    };

    const handleSelectAll = () => {
        const allIds = new Set(filteredExtractedQuestions.map((q) => q.id));
        setSelectedQuestionIds(allIds);
    };

    const handleDeselectAll = () => {
        setSelectedQuestionIds(new Set());
    };

    const handleSelectAllExceptExcluded = () => {
        const allIds = filteredExtractedQuestions.map((q) => q.id);
        const next = new Set();
        allIds.forEach((id) => {
            if (!selectedQuestionIds.has(id)) {
                next.add(id);
            }
        });
        setSelectedQuestionIds(next);
    };

    const handleBulkApprove = async () => {
        if (selectedQuestionIds.size === 0) return;

        setIsBulkApproving(true);
        setExtractionError(null);
        setExtractionSuccess(null);

        const idsToApprove = Array.from(selectedQuestionIds);

        try {
            const res = await bulkApproveQuestions(idsToApprove);
            if (res && res.success) {
                const data = res.data || {};
                setBulkSummary(data);
                setIsBulkSummaryOpen(true);

                const approvedSet = new Set(data.approved_ids || []);
                setExtractedQuestions((prev) =>
                    prev.map((item) =>
                        approvedSet.has(item.id) ? { ...item, status: "review" } : item
                    )
                );
                setBankQuestions((prev) =>
                    prev.map((item) =>
                        approvedSet.has(item.id) ? { ...item, status: "review" } : item
                    )
                );

                // Quitar de la selección las aprobadas
                setSelectedQuestionIds((prev) => {
                    const next = new Set(prev);
                    approvedSet.forEach((id) => next.delete(id));
                    return next;
                });

                setExtractionSuccess(
                    `Aprobación masiva finalizada: ${data.total_approved} preguntas aprobadas exitosamente.`
                );
                fetchBankQuestions();
            } else {
                setExtractionError(res?.message || "Error al procesar la aprobación masiva.");
            }
        } catch (err) {
            console.error("Error en aprobación masiva:", err);
            const msg = err.response?.data?.message || err.message || "Fallo al ejecutar la aprobación masiva.";
            setExtractionError(msg);
        } finally {
            setIsBulkApproving(false);
        }
    };

    const handlePromptReject = (q) => {
        setQuestionToReject(q);
        setIsConfirmRejectOpen(true);
    };

    const handleConfirmReject = async () => {
        if (!questionToReject) return;
        setActionLoadingId(questionToReject.id);
        setExtractionError(null);
        setExtractionSuccess(null);

        try {
            const res = await rejectQuestion(questionToReject.id);
            if (res && res.success) {
                setExtractionSuccess(
                    `Pregunta #${questionToReject.order_in_group || questionToReject.id.slice(0, 8)} rechazada y archivada. Los estímulos compartidos permanecen intactos.`
                );
                setExtractedQuestions((prev) =>
                    prev.map((item) => (item.id === questionToReject.id ? { ...item, ...res.data } : item))
                );
                setBankQuestions((prev) =>
                    prev.map((item) => (item.id === questionToReject.id ? { ...item, ...res.data } : item))
                );
                fetchBankQuestions();
                setIsConfirmRejectOpen(false);
                setQuestionToReject(null);
            } else {
                setExtractionError(res?.message || "No fue posible rechazar la pregunta.");
            }
        } catch (err) {
            console.error("Error al rechazar pregunta:", err);
            const msg = err.response?.data?.message || err.message || "Fallo al rechazar la pregunta.";
            setExtractionError(msg);
        } finally {
            setActionLoadingId(null);
        }
    };

    // Objeto del documento seleccionado para extracción
    const selectedExtractionDocObj = useMemo(() => {
        return documents.find((d) => d.id === selectedExtractionDocId) || null;
    }, [documents, selectedExtractionDocId]);

    // Metadatos de auditoría de calidad automática registrados en PostgreSQL
    const qualityAudit = useMemo(() => {
        return extractionStatusData?.extraction_metadata?.quality_audit || null;
    }, [extractionStatusData]);

    // Métricas y contadores de calidad pedagógica
    const auditCounts = useMemo(() => {
        const total = extractedQuestions.length;
        const issues = extractedQuestions.filter((q) => Boolean(q.extraction_warnings)).length;
        const ready = extractedQuestions.filter((q) => !q.extraction_warnings && q.status !== "rejected").length;
        const verifiedKeys = extractedQuestions.filter((q) => q.key_status === "verified").length;
        return { total, issues, ready, verifiedKeys };
    }, [extractedQuestions]);

    // Preguntas filtradas por la pestaña activa de auditoría
    const filteredExtractedQuestions = useMemo(() => {
        if (!extractedQuestions || extractedQuestions.length === 0) return [];
        if (auditFilter === "ready") {
            return extractedQuestions.filter((q) => !q.extraction_warnings);
        }
        if (auditFilter === "issues") {
            return extractedQuestions.filter((q) => Boolean(q.extraction_warnings));
        }
        return extractedQuestions;
    }, [extractedQuestions, auditFilter]);

    // Agrupamiento de estímulos compartidos vs preguntas independientes respetando el filtro activo
    const groupedExtraction = useMemo(() => {
        if (!filteredExtractedQuestions || filteredExtractedQuestions.length === 0) {
            return { stimuliGroups: [], standaloneQuestions: [] };
        }

        const stimuliMap = new Map();
        const standalone = [];

        filteredExtractedQuestions.forEach((q) => {
            if (q.stimulus_id && q.stimulus) {
                if (!stimuliMap.has(q.stimulus_id)) {
                    stimuliMap.set(q.stimulus_id, {
                        stimulus: q.stimulus,
                        questions: [],
                    });
                }
                stimuliMap.get(q.stimulus_id).questions.push(q);
            } else {
                standalone.push(q);
            }
        });

        return {
            stimuliGroups: Array.from(stimuliMap.values()),
            standaloneQuestions: standalone,
        };
    }, [filteredExtractedQuestions]);

    useEffect(() => {
        if (activeTab === "ai_extraction") {
            if (documents.length === 0 && !isLoadingDocs) {
                fetchDocuments();
            }
        }
    }, [activeTab, documents.length, isLoadingDocs, fetchDocuments]);

    useEffect(() => {
        if (activeTab === "ai_extraction" && documents.length > 0 && !selectedExtractionDocId) {
            setSelectedExtractionDocId(documents[0].id);
        }
    }, [activeTab, documents, selectedExtractionDocId]);

    useEffect(() => {
        if (activeTab === "ai_extraction" && selectedExtractionDocId) {
            loadExtractionData(selectedExtractionDocId);
        }
    }, [activeTab, selectedExtractionDocId, loadExtractionData]);


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
                    className={`qst-tab-btn ${activeTab === "questions" ? "qst-tab-btn--active" : ""}`}
                    onClick={() => setActiveTab("questions")}
                >
                    <i className="fa-solid fa-layer-group"></i>
                    <span>Banco de Preguntas</span>
                    <span className="qst-tab-badge">{bankStats.total_bank}</span>
                </button>

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
                    className={`qst-tab-btn ${activeTab === "ai_extraction" ? "qst-tab-btn--active" : ""}`}
                    onClick={() => setActiveTab("ai_extraction")}
                >
                    <i className="fa-solid fa-wand-magic-sparkles"></i>
                    <span>Extracción y Revisión IA</span>
                    <span className="qst-tab-badge qst-tab-badge--muted">
                        {extractedQuestions.length > 0 ? `${extractedQuestions.length}` : "Gemini"}
                    </span>
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
                                                            doc.status === "extracted"
                                                                ? "qst-doc-badge--extracted"
                                                                : doc.status === "uploaded"
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
                                                            className="qst-action-btn"
                                                            title="Extraer o revisar con Gemini"
                                                            type="button"
                                                            onClick={() => handleGoToExtraction(doc)}
                                                            style={{ color: "#c084fc", borderColor: "rgba(168, 85, 247, 0.35)" }}
                                                        >
                                                            <i className="fa-solid fa-wand-magic-sparkles"></i>
                                                        </button>
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
                    {/* MENSAJE DE ERROR DEL BANCO */}
                    {bankError && (
                        <div className="qst-alert qst-alert--error">
                            <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
                                <i className="fa-solid fa-triangle-exclamation"></i>
                                <span>{bankError}</span>
                            </div>
                            <button
                                className="qst-alert-close"
                                onClick={() => setBankError(null)}
                                aria-label="Cerrar error"
                            >
                                <i className="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    )}

                    {/* TARJETAS KPI DE ESTADÍSTICAS REALES */}
                    <section className="qst-stats-grid">
                        <div className="qst-stat-card">
                            <div className="qst-stat-info">
                                <span className="qst-stat-label">Banco Oficial (Aprobadas)</span>
                                <span className="qst-stat-val">{bankStats.total_bank}</span>
                            </div>
                            <div className="qst-stat-icon-wrap qst-stat-icon--green">
                                <i className="fa-solid fa-layer-group"></i>
                            </div>
                        </div>

                        <div className="qst-stat-card">
                            <div className="qst-stat-info">
                                <span className="qst-stat-label">Publicadas para Examen</span>
                                <span className="qst-stat-val">{bankStats.published_count}</span>
                            </div>
                            <div className="qst-stat-icon-wrap qst-stat-icon--blue">
                                <i className="fa-solid fa-circle-check"></i>
                            </div>
                        </div>

                        <div className="qst-stat-card">
                            <div className="qst-stat-info">
                                <span className="qst-stat-label">En Calibración Pedagógica</span>
                                <span className="qst-stat-val">{bankStats.review_count}</span>
                            </div>
                            <div className="qst-stat-icon-wrap qst-stat-icon--amber">
                                <i className="fa-solid fa-clock-rotate-left"></i>
                            </div>
                        </div>

                        <div className="qst-stat-card">
                            <div className="qst-stat-info">
                                <span className="qst-stat-label">Claves Verificadas</span>
                                <span className="qst-stat-val">{bankStats.verified_count}</span>
                            </div>
                            <div className="qst-stat-icon-wrap qst-stat-icon--purple">
                                <i className="fa-solid fa-key"></i>
                            </div>
                        </div>
                    </section>

                    {/* BARRA DE HERRAMIENTAS Y FILTROS REALES */}
                    <section className="qst-tools-bar">
                        <div className="qst-search-box">
                            <i className="fa-solid fa-magnifying-glass qst-search-icon"></i>
                            <input
                                type="text"
                                placeholder="Buscar en el enunciado..."
                                className="qst-search-input"
                                value={bankSearch}
                                onChange={(e) => setBankSearch(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === "Enter") {
                                        fetchBankQuestions(1);
                                    }
                                }}
                            />
                            {bankSearch && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        setBankSearch("");
                                        fetchBankQuestions(1);
                                    }}
                                    style={{ background: "transparent", border: "none", color: "#94a3b8", cursor: "pointer", marginRight: "10px" }}
                                    title="Limpiar búsqueda"
                                >
                                    <i className="fa-solid fa-xmark"></i>
                                </button>
                            )}
                        </div>

                        <div className="qst-filters-group">
                            <div className="qst-select-wrap">
                                <label className="qst-select-label" htmlFor="filter-bank-subject">
                                    Materia:
                                </label>
                                <select
                                    id="filter-bank-subject"
                                    className="qst-select"
                                    value={bankSubjectFilter}
                                    onChange={(e) => {
                                        setBankSubjectFilter(e.target.value);
                                        setBankPage(1);
                                    }}
                                >
                                    <option value="Todas">Todas las materias</option>
                                    <option value="1">Lectura Crítica</option>
                                    <option value="2">Matemáticas</option>
                                    <option value="3">Ciencias Naturales</option>
                                    <option value="4">Sociales y Ciudadanas</option>
                                    <option value="5">Inglés</option>
                                </select>
                            </div>

                            <div className="qst-select-wrap">
                                <label className="qst-select-label" htmlFor="filter-bank-status">
                                    Estado:
                                </label>
                                <select
                                    id="filter-bank-status"
                                    className="qst-select"
                                    value={bankStatusFilter}
                                    onChange={(e) => {
                                        setBankStatusFilter(e.target.value);
                                        setBankPage(1);
                                    }}
                                >
                                    <option value="official">Oficiales (Revisadas y Publicadas)</option>
                                    <option value="published">Publicadas (published)</option>
                                    <option value="review">En Revisión / Aprobadas (review)</option>
                                    <option value="draft">Borradores IA (draft)</option>
                                    <option value="archived">Archivadas / Rechazadas (archived)</option>
                                    <option value="all">Todas (Modo Auditoría)</option>
                                </select>
                            </div>

                            <div className="qst-select-wrap">
                                <label className="qst-select-label" htmlFor="filter-bank-perpage">
                                    Mostrar:
                                </label>
                                <select
                                    id="filter-bank-perpage"
                                    className="qst-select"
                                    value={bankPerPage}
                                    onChange={(e) => {
                                        const val = Number(e.target.value);
                                        setBankPerPage(val);
                                        setBankPage(1);
                                    }}
                                >
                                    <option value={10}>10 por página</option>
                                    <option value={20}>20 por página</option>
                                    <option value={50}>Todas (50 por página)</option>
                                </select>
                            </div>

                            <button
                                className="qst-btn-excel"
                                onClick={() => fetchBankQuestions(bankPage)}
                                type="button"
                                title="Recargar preguntas desde PostgreSQL"
                                disabled={isLoadingBank}
                            >
                                <i className={`fa-solid fa-arrows-rotate ${isLoadingBank ? "fa-spin" : ""}`}></i>
                                <span>Refrescar</span>
                            </button>
                        </div>
                    </section>

                    {/* LISTADO DE PREGUNTAS REALES CON PAGINACIÓN Y ESTÍMULOS COMPARTIDOS */}
                    <section className="qst-table-card">
                        {/* BARRA SUPERIOR DE PAGINACIÓN Y CONTROLES DE PÁGINA */}
                        {bankQuestions.length > 0 && (
                            <div
                                className="qst-table-footer"
                                style={{
                                    display: "flex",
                                    justifyContent: "space-between",
                                    alignItems: "center",
                                    flexWrap: "wrap",
                                    gap: "12px",
                                    borderTop: "none",
                                    borderBottom: "1px solid var(--qst-border)",
                                    background: "rgba(15, 23, 42, 0.55)",
                                }}
                            >
                                <div style={{ display: "flex", alignItems: "center", gap: "12px", flexWrap: "wrap" }}>
                                    <span>
                                        Mostrando reactivos{" "}
                                        <strong>
                                            {bankPagination.from ?? 1}–{bankPagination.to ?? bankQuestions.length}
                                        </strong>{" "}
                                        de <strong>{bankPagination.total}</strong> en orden oficial (Pregunta #
                                        {bankQuestions[0]?.order_in_group ?? 1} a #
                                        {bankQuestions[bankQuestions.length - 1]?.order_in_group ?? bankQuestions.length})
                                    </span>
                                    <div style={{ display: "flex", gap: "6px" }}>
                                        <button
                                            type="button"
                                            className="qst-btn-outline"
                                            style={{ padding: "3px 8px", fontSize: "0.75rem" }}
                                            onClick={() => setExpandedBankIds(new Set(bankQuestions.map((q) => q.id)))}
                                        >
                                            <i className="fa-solid fa-angles-down"></i> Expandir Opciones
                                        </button>
                                        <button
                                            type="button"
                                            className="qst-btn-outline"
                                            style={{ padding: "3px 8px", fontSize: "0.75rem" }}
                                            onClick={() => setExpandedBankIds(new Set())}
                                        >
                                            <i className="fa-solid fa-angles-up"></i> Colapsar Opciones
                                        </button>
                                    </div>
                                </div>

                                <div style={{ display: "flex", alignItems: "center", gap: "6px", flexWrap: "wrap" }}>
                                    <button
                                        type="button"
                                        className="qst-btn-outline"
                                        style={{ padding: "5px 10px", fontSize: "0.8rem" }}
                                        disabled={bankPagination.current_page <= 1 || isLoadingBank}
                                        onClick={() => fetchBankQuestions(bankPagination.current_page - 1)}
                                    >
                                        <i className="fa-solid fa-chevron-left"></i> Anterior
                                    </button>

                                    {Array.from({ length: bankPagination.last_page || 1 }, (_, i) => i + 1).map((pageNum) => {
                                        const isCurrent = pageNum === bankPagination.current_page;
                                        return (
                                            <button
                                                key={pageNum}
                                                type="button"
                                                disabled={isLoadingBank}
                                                onClick={() => fetchBankQuestions(pageNum)}
                                                style={{
                                                    minWidth: "32px",
                                                    height: "30px",
                                                    padding: "0 8px",
                                                    borderRadius: "6px",
                                                    fontSize: "0.8rem",
                                                    fontWeight: 700,
                                                    cursor: isLoadingBank ? "not-allowed" : "pointer",
                                                    border: isCurrent
                                                        ? "1px solid #22c55e"
                                                        : "1px solid rgba(148, 163, 184, 0.25)",
                                                    background: isCurrent
                                                        ? "rgba(34, 197, 94, 0.2)"
                                                        : "rgba(15, 23, 42, 0.6)",
                                                    color: isCurrent ? "#4ade80" : "#cbd5e1",
                                                }}
                                            >
                                                {pageNum}
                                            </button>
                                        );
                                    })}

                                    <button
                                        type="button"
                                        className="qst-btn-outline"
                                        style={{ padding: "5px 10px", fontSize: "0.8rem" }}
                                        disabled={bankPagination.current_page >= bankPagination.last_page || isLoadingBank}
                                        onClick={() => fetchBankQuestions(bankPagination.current_page + 1)}
                                    >
                                        Siguiente <i className="fa-solid fa-chevron-right"></i>
                                    </button>

                                    <span style={{ fontSize: "0.8rem", color: "#94a3b8", marginLeft: "4px" }}>
                                        Página <strong>{bankPagination.current_page}</strong> de{" "}
                                        <strong>{bankPagination.last_page}</strong>
                                    </span>
                                </div>
                            </div>
                        )}

                        {isLoadingBank ? (
                            <div style={{ textAlign: "center", padding: "60px 20px" }}>
                                <span className="qst-spinner" style={{ width: "36px", height: "36px", borderWidth: "3px" }}></span>
                                <p style={{ color: "#94a3b8", fontSize: "0.95rem", marginTop: "14px" }}>
                                    Consultando preguntas reales en Supabase PostgreSQL...
                                </p>
                            </div>
                        ) : bankQuestions.length > 0 ? (
                            <div style={{ display: "flex", flexDirection: "column", gap: "22px", padding: "18px" }}>
                                {groupedBankBlocks.map((block, bIdx) => {
                                    if (block.type === "stimulus") {
                                        const stim = block.stimulus;
                                        return (
                                            <div key={`bank-stim-${stim.id}-${bIdx}`} className="qst-stimulus-container">
                                                <div className="qst-stimulus-header">
                                                    <div className="qst-stimulus-title-box">
                                                        <i className="fa-solid fa-book-open-reader"></i>
                                                        <div>
                                                            <h3 className="qst-stimulus-title">
                                                                {stim.title || "Estímulo de Lectura Compartido"}
                                                            </h3>
                                                            <span style={{ fontSize: "0.75rem", color: "#94a3b8" }}>
                                                                Estímulo compartido • Pregunta(s) en esta página:{" "}
                                                                {block.questions.map((q) => `#${q.order_in_group || "?"}`).join(", ")}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <span className="qst-stimulus-badge-count">
                                                        <i className="fa-solid fa-link"></i>
                                                        {block.questions.length} reactivo(s) en vista
                                                    </span>
                                                </div>

                                                {stim.instructions && (
                                                    <div className="qst-stimulus-instructions">
                                                        <i className="fa-solid fa-circle-info"></i>
                                                        <span>{stim.instructions}</span>
                                                    </div>
                                                )}

                                                {stim.content_text && (
                                                    <div className="qst-stimulus-content-box">
                                                        {stim.content_text}
                                                    </div>
                                                )}

                                                <div style={{ padding: "0 4px 8px" }}>
                                                    <StimulusMedia stimulus={stim} />
                                                </div>

                                                <div className="qst-stimulus-questions-list">
                                                    {block.questions.map((q) => (
                                                        <QuestionCardItem
                                                            key={q.id}
                                                            q={q}
                                                            isExpanded={expandedBankIds.has(q.id)}
                                                            onToggleExpand={() => toggleBankExpand(q.id)}
                                                            onEdit={handleOpenEdit}
                                                            onApprove={handleApprove}
                                                            onReject={handlePromptReject}
                                                            isProcessingAction={actionLoadingId === q.id}
                                                        />
                                                    ))}
                                                </div>
                                            </div>
                                        );
                                    }

                                    const q = block.question;
                                    return (
                                        <QuestionCardItem
                                            key={q.id}
                                            q={q}
                                            isExpanded={expandedBankIds.has(q.id)}
                                            onToggleExpand={() => toggleBankExpand(q.id)}
                                            onEdit={handleOpenEdit}
                                            onApprove={handleApprove}
                                            onReject={handlePromptReject}
                                            isProcessingAction={actionLoadingId === q.id}
                                        />
                                    );
                                })}
                            </div>
                        ) : (
                            <div className="qst-empty-state" style={{ padding: "50px 20px" }}>
                                <i className="fa-solid fa-folder-open" style={{ fontSize: "2.5rem", color: "#64748b" }}></i>
                                <h3 style={{ color: "#ffffff", fontSize: "1.15rem", margin: "14px 0 6px 0" }}>
                                    {bankStatusFilter === "official"
                                        ? "No hay preguntas en el banco oficial actualmente"
                                        : "No se encontraron preguntas con los filtros seleccionados"}
                                </h3>
                                <p style={{ color: "#94a3b8", fontSize: "0.88rem", maxWidth: "560px", lineHeight: 1.5, margin: "0 auto" }}>
                                    {bankStatusFilter === "official" ? (
                                        <>
                                            El banco oficial muestra únicamente preguntas certificadas en estado <strong>review</strong> (aprobadas) o <strong>published</strong> (publicadas). Actualmente hay <strong>{bankStats.draft_count}</strong> reactivo(s) en borrador en la base de datos Supabase esperando calibración pedagógica.
                                        </>
                                    ) : (
                                        "Intenta cambiar el criterio de búsqueda o el área académica seleccionada."
                                    )}
                                </p>
                                <div style={{ display: "flex", gap: "12px", justifyContent: "center", marginTop: "18px", flexWrap: "wrap" }}>
                                    {bankStats.draft_count > 0 && bankStatusFilter === "official" && (
                                        <button
                                            type="button"
                                            className="qst-btn-save"
                                            onClick={() => {
                                                setBankStatusFilter("draft");
                                                setBankPage(1);
                                            }}
                                        >
                                            <i className="fa-solid fa-clock-rotate-left"></i>
                                            <span>Ver Borradores ({bankStats.draft_count})</span>
                                        </button>
                                    )}
                                    <button
                                        type="button"
                                        className="qst-btn-save"
                                        style={{ background: "linear-gradient(135deg, #a855f7 0%, #7e22ce 100%)", boxShadow: "0 4px 12px rgba(168, 85, 247, 0.3)" }}
                                        onClick={() => setActiveTab("ai_extraction")}
                                    >
                                        <i className="fa-solid fa-wand-magic-sparkles"></i>
                                        <span>Ir a Extracción y Revisión IA</span>
                                    </button>
                                </div>
                            </div>
                        )}

                        {/* PIE DE TABLA Y PAGINACIÓN REAL */}
                        <div className="qst-table-footer" style={{ display: "flex", justifyContent: "space-between", alignItems: "center", flexWrap: "wrap", gap: "12px" }}>
                            <span>
                                Mostrando{" "}
                                <strong>
                                    {bankPagination.from ?? (bankQuestions.length > 0 ? 1 : 0)}–
                                    {bankPagination.to ?? bankQuestions.length}
                                </strong>{" "}
                                de <strong>{bankPagination.total}</strong> reactivos registrados en Supabase
                            </span>

                            {bankPagination.last_page >= 1 && bankPagination.total > 0 && (
                                <div style={{ display: "flex", alignItems: "center", gap: "6px", flexWrap: "wrap" }}>
                                    <button
                                        type="button"
                                        className="qst-btn-outline"
                                        style={{ padding: "5px 10px", fontSize: "0.8rem" }}
                                        disabled={bankPagination.current_page <= 1 || isLoadingBank}
                                        onClick={() => fetchBankQuestions(bankPagination.current_page - 1)}
                                    >
                                        <i className="fa-solid fa-chevron-left"></i> Anterior
                                    </button>

                                    {Array.from({ length: bankPagination.last_page || 1 }, (_, i) => i + 1).map((pageNum) => {
                                        const isCurrent = pageNum === bankPagination.current_page;
                                        return (
                                            <button
                                                key={pageNum}
                                                type="button"
                                                disabled={isLoadingBank}
                                                onClick={() => fetchBankQuestions(pageNum)}
                                                style={{
                                                    minWidth: "32px",
                                                    height: "30px",
                                                    padding: "0 8px",
                                                    borderRadius: "6px",
                                                    fontSize: "0.8rem",
                                                    fontWeight: 700,
                                                    cursor: isLoadingBank ? "not-allowed" : "pointer",
                                                    border: isCurrent
                                                        ? "1px solid #22c55e"
                                                        : "1px solid rgba(148, 163, 184, 0.25)",
                                                    background: isCurrent
                                                        ? "rgba(34, 197, 94, 0.2)"
                                                        : "rgba(15, 23, 42, 0.6)",
                                                    color: isCurrent ? "#4ade80" : "#cbd5e1",
                                                }}
                                            >
                                                {pageNum}
                                            </button>
                                        );
                                    })}

                                    <button
                                        type="button"
                                        className="qst-btn-outline"
                                        style={{ padding: "5px 10px", fontSize: "0.8rem" }}
                                        disabled={bankPagination.current_page >= bankPagination.last_page || isLoadingBank}
                                        onClick={() => fetchBankQuestions(bankPagination.current_page + 1)}
                                    >
                                        Siguiente <i className="fa-solid fa-chevron-right"></i>
                                    </button>

                                    <span style={{ fontSize: "0.82rem", color: "#94a3b8", marginLeft: "4px" }}>
                                        Página <strong>{bankPagination.current_page}</strong> de{" "}
                                        <strong>{bankPagination.last_page}</strong>
                                    </span>
                                </div>
                            )}
                        </div>
                    </section>
                </>
            )}

            {/* =========================================================
                PESTAÑA 3: EXTRACCIÓN Y REVISIÓN IA (GEMINI MULTIMODAL)
                ========================================================= */}
            {activeTab === "ai_extraction" && (
                <div className="qst-extraction-section">
                    {/* ALERTAS ESPECÍFICAS DE EXTRACCIÓN */}
                    {extractionSuccess && (
                        <div className="qst-alert qst-alert--success">
                            <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
                                <i className="fa-solid fa-circle-check"></i>
                                <span>{extractionSuccess}</span>
                            </div>
                            <button
                                className="qst-alert-close"
                                onClick={() => setExtractionSuccess(null)}
                                aria-label="Cerrar notificación"
                            >
                                <i className="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    )}

                    {extractionError && (
                        <div className="qst-alert qst-alert--error">
                            <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
                                <i className="fa-solid fa-triangle-exclamation"></i>
                                <span>{extractionError}</span>
                            </div>
                            <button
                                className="qst-alert-close"
                                onClick={() => setExtractionError(null)}
                                aria-label="Cerrar error"
                            >
                                <i className="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    )}

                    {/* PANEL DE CONTROL SUPERIOR */}
                    <section className="qst-extraction-control-panel">
                        <div className="qst-extraction-header-row">
                            <div className="qst-extraction-title-group">
                                <h2>
                                    <i className="fa-solid fa-wand-magic-sparkles"></i>
                                    Extracción Multimodal con Gemini (Question Bank)
                                </h2>
                                <p>
                                    Selecciona un cuadernillo PDF alojado en Supabase Storage para ejecutar el modelo de visión multimodal. Las preguntas y lecturas detectadas se estructurarán como borradores editables respetando estímulos compartidos y validación de claves.
                                </p>
                            </div>
                        </div>

                        {/* FILA DE SELECTOR DE DOCUMENTO Y BOTÓN */}
                        <div className="qst-extraction-selector-row">
                            <div className="qst-extraction-selector-field">
                                <label>
                                    <i className="fa-solid fa-file-pdf"></i> Cuadernillo Fuente (PDF)
                                </label>
                                <select
                                    className="qst-extraction-select"
                                    value={selectedExtractionDocId}
                                    onChange={(e) => setSelectedExtractionDocId(e.target.value)}
                                    disabled={isExtracting || documents.length === 0}
                                >
                                    {documents.length === 0 ? (
                                        <option value="">No hay documentos cargados en el repositorio</option>
                                    ) : (
                                        documents.map((d) => (
                                            <option key={d.id} value={d.id}>
                                                {d.title} ({d.year || "S/A"} - {d.exam_type || "Saber 11"}) — [{d.status}]
                                            </option>
                                        ))
                                    )}
                                </select>
                            </div>

                            <div className="qst-extraction-actions" style={{ display: "flex", gap: "8px", alignItems: "center", flexWrap: "wrap" }}>
                                <button
                                    type="button"
                                    className="qst-btn-gemini-extract"
                                    onClick={handleTriggerExtraction}
                                    disabled={!selectedExtractionDocId || isExtracting}
                                    title="Ejecutar análisis y extracción de preguntas con Gemini"
                                >
                                    {isExtracting ? (
                                        <>
                                            <span className="qst-spinner" style={{ width: "16px", height: "16px", borderWidth: "2px" }}></span>
                                            <span>{iqpeJobData?.progress || "Procesando en segundo plano..."}</span>
                                        </>
                                    ) : (
                                        <>
                                            <i className="fa-solid fa-bolt-lightning"></i>
                                            <span>
                                                {(selectedExtractionDocObj?.status === "extracted" || iqpeJobData?.status === "completed")
                                                    ? "Re-extraer con Gemini"
                                                    : "Extraer preguntas con Gemini"}
                                            </span>
                                        </>
                                    )}
                                </button>

                                {/* Botón para reintentar sin volver a subir el PDF */}
                                {((selectedExtractionDocObj?.status === "failed" || iqpeJobData?.status === "failed") && !isExtracting) && (
                                    <button
                                        type="button"
                                        onClick={handleRetryProcessing}
                                        style={{
                                            display: "inline-flex",
                                            alignItems: "center",
                                            gap: "8px",
                                            background: "rgba(239, 68, 68, 0.15)",
                                            color: "#f87171",
                                            border: "1px solid rgba(239, 68, 68, 0.35)",
                                            padding: "9px 16px",
                                            borderRadius: "8px",
                                            cursor: "pointer",
                                            fontWeight: 700,
                                            fontSize: "0.85rem",
                                        }}
                                        title="Reintentar procesamiento IQPE sin volver a subir el documento"
                                    >
                                        <i className="fa-solid fa-rotate-right"></i>
                                        <span>Reintentar procesamiento</span>
                                    </button>
                                )}

                                <button
                                    type="button"
                                    className="qst-btn-refresh-data"
                                    onClick={() => loadExtractionData(selectedExtractionDocId)}
                                    disabled={!selectedExtractionDocId || isLoadingExtractionData}
                                    title="Actualizar estado y preguntas"
                                >
                                    <i className={`fa-solid fa-arrows-rotate ${isLoadingExtractionData ? "fa-spin" : ""}`}></i>
                                </button>
                            </div>
                        </div>

                        {/* KPIS DE ESTADO DE EXTRACCIÓN Y AUDITORÍA DE CALIDAD */}
                        {selectedExtractionDocId && (
                            <div className="qst-extraction-kpis">
                                {/* KPI 1: Documento y Estado de Procesamiento */}
                                <div className="qst-extraction-kpi-card">
                                    <div style={{ overflow: "hidden" }}>
                                        <div className="qst-extraction-kpi-label" style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                                            <span>Estado IQPE</span>
                                            {iqpeJobData?.run_number > 0 && (
                                                <span style={{ fontSize: "0.68rem", background: "rgba(148, 163, 184, 0.2)", padding: "1px 6px", borderRadius: "4px", color: "#94a3b8" }}>
                                                    Corrida #{iqpeJobData.run_number}
                                                </span>
                                            )}
                                        </div>
                                        <div className="qst-extraction-kpi-val" style={{ fontSize: "0.95rem", marginTop: "4px" }}>
                                            <span
                                                className={`qst-doc-badge ${
                                                    (iqpeJobData?.status || extractionStatusData?.status || selectedExtractionDocObj?.status) === "completed" || (selectedExtractionDocObj?.status === "extracted")
                                                        ? "qst-doc-badge--extracted"
                                                        : (iqpeJobData?.status || extractionStatusData?.status || selectedExtractionDocObj?.status) === "uploaded" || (iqpeJobData?.status === "queued")
                                                        ? "qst-doc-badge--uploaded"
                                                        : ["extracting", "solving_round_1", "solving_round_2", "processing"].includes(iqpeJobData?.status || extractionStatusData?.status || selectedExtractionDocObj?.status)
                                                        ? "qst-doc-badge--processing"
                                                        : "qst-doc-badge--failed"
                                                }`}
                                            >
                                                <span className="qst-status-dot"></span>
                                                {iqpeJobData?.status || extractionStatusData?.status || selectedExtractionDocObj?.status || "Desconocido"}
                                            </span>
                                        </div>
                                        <div
                                            style={{
                                                fontSize: "0.72rem",
                                                color: (iqpeJobData?.status === "failed") ? "#f87171" : "#94a3b8",
                                                marginTop: "4px",
                                                lineHeight: "1.2",
                                            }}
                                            title={iqpeJobData?.progress || selectedExtractionDocObj?.title}
                                        >
                                            {iqpeJobData?.progress || selectedExtractionDocObj?.title || "Sin actividad"}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: "0.74rem",
                                                color: "#94a3b8",
                                                marginTop: "4px",
                                                whiteSpace: "nowrap",
                                                overflow: "hidden",
                                                textOverflow: "ellipsis",
                                                maxWidth: "150px",
                                            }}
                                            title={selectedExtractionDocObj?.title}
                                        >
                                            {selectedExtractionDocObj?.title || "Sin título"}
                                        </div>
                                    </div>
                                    <div className="qst-extraction-kpi-icon" style={{ background: "rgba(168, 85, 247, 0.15)", color: "#c084fc" }}>
                                        <i className="fa-solid fa-server"></i>
                                    </div>
                                </div>

                                {/* KPI 2: Área Detectada */}
                                <div className="qst-extraction-kpi-card">
                                    <div>
                                        <div className="qst-extraction-kpi-label">Área Detectada</div>
                                        <div className="qst-extraction-kpi-val" style={{ fontSize: "1.05rem", color: "#38bdf8" }}>
                                            {qualityAudit?.detected_subject || (selectedExtractionDocObj?.subject_id === 1 ? "Lectura Crítica" : selectedExtractionDocObj?.subject_id === 2 ? "Matemáticas" : "General ICFES")}
                                        </div>
                                        <div style={{ fontSize: "0.72rem", color: "#64748b", marginTop: "2px" }}>
                                            {selectedExtractionDocObj?.exam_type || "Saber 11"} ({selectedExtractionDocObj?.year || "2026"})
                                        </div>
                                    </div>
                                    <div className="qst-extraction-kpi-icon" style={{ background: "rgba(56, 189, 248, 0.15)", color: "#38bdf8" }}>
                                        <i className="fa-solid fa-graduation-cap"></i>
                                    </div>
                                </div>

                                {/* KPI 3: Total Preguntas Extraídas */}
                                <div className="qst-extraction-kpi-card">
                                    <div>
                                        <div className="qst-extraction-kpi-label">Total Preguntas</div>
                                        <div className="qst-extraction-kpi-val">
                                            {qualityAudit?.total_questions ?? extractedQuestions.length}
                                        </div>
                                        <div style={{ fontSize: "0.72rem", color: "#64748b", marginTop: "2px" }}>
                                            Reactivos en documento
                                        </div>
                                    </div>
                                    <div className="qst-extraction-kpi-icon" style={{ background: "rgba(96, 165, 250, 0.15)", color: "#60a5fa" }}>
                                        <i className="fa-solid fa-list-ol"></i>
                                    </div>
                                </div>

                                {/* KPI 4: Listas para Aprobación */}
                                <div className="qst-extraction-kpi-card">
                                    <div>
                                        <div className="qst-extraction-kpi-label">Listas para Aprobación</div>
                                        <div className="qst-extraction-kpi-val" style={{ color: "#4ade80" }}>
                                            {qualityAudit?.ready_for_approval_count ?? auditCounts.ready}
                                        </div>
                                        <div style={{ fontSize: "0.72rem", color: "#4ade80", marginTop: "2px" }}>
                                            Sin observaciones pendientes
                                        </div>
                                    </div>
                                    <div className="qst-extraction-kpi-icon" style={{ background: "rgba(34, 197, 94, 0.15)", color: "#22c55e" }}>
                                        <i className="fa-solid fa-circle-check"></i>
                                    </div>
                                </div>

                                {/* KPI 5: Con Observaciones o Anomalías */}
                                <div className="qst-extraction-kpi-card">
                                    <div>
                                        <div className="qst-extraction-kpi-label">Con Observaciones</div>
                                        <div
                                            className="qst-extraction-kpi-val"
                                            style={{ color: (qualityAudit?.requires_review_count ?? auditCounts.issues) > 0 ? "#fbbf24" : "#94a3b8" }}
                                        >
                                            {qualityAudit?.requires_review_count ?? auditCounts.issues}
                                        </div>
                                        <div style={{ fontSize: "0.72rem", color: (qualityAudit?.requires_review_count ?? auditCounts.issues) > 0 ? "#fbbf24" : "#64748b", marginTop: "2px" }}>
                                            {(qualityAudit?.requires_review_count ?? auditCounts.issues) > 0 ? "Requieren calibración" : "0 anomalías"}
                                        </div>
                                    </div>
                                    <div
                                        className="qst-extraction-kpi-icon"
                                        style={{
                                            background: (qualityAudit?.requires_review_count ?? auditCounts.issues) > 0 ? "rgba(245, 158, 11, 0.15)" : "rgba(148, 163, 184, 0.1)",
                                            color: (qualityAudit?.requires_review_count ?? auditCounts.issues) > 0 ? "#f59e0b" : "#94a3b8",
                                        }}
                                    >
                                        <i className="fa-solid fa-triangle-exclamation"></i>
                                    </div>
                                </div>

                                {/* KPI 6: Claves Oficiales Verificadas vs No Verificadas */}
                                <div className="qst-extraction-kpi-card">
                                    <div>
                                        <div className="qst-extraction-kpi-label">Claves Oficiales</div>
                                        <div className="qst-extraction-kpi-val" style={{ fontSize: "1.1rem" }}>
                                            {qualityAudit?.verified_keys_count ?? auditCounts.verifiedKeys} / {qualityAudit?.total_questions ?? extractedQuestions.length}
                                        </div>
                                        <div style={{ fontSize: "0.72rem", color: qualityAudit?.has_official_answer_key ? "#4ade80" : "#94a3b8", marginTop: "2px" }}>
                                            {qualityAudit?.has_official_answer_key ? "Tabla oficial PDF validada" : "Claves pedagógicas"}
                                        </div>
                                    </div>
                                    <div className="qst-extraction-kpi-icon" style={{ background: "rgba(52, 211, 153, 0.15)", color: "#34d399" }}>
                                        <i className="fa-solid fa-key"></i>
                                    </div>
                                </div>

                                {/* KPI 7: Estímulos y Medios Asociados / Pendientes */}
                                <div className="qst-extraction-kpi-card">
                                    <div>
                                        <div className="qst-extraction-kpi-label">Estímulos & Recursos</div>
                                        <div className="qst-extraction-kpi-val" style={{ fontSize: "1.05rem" }}>
                                            {qualityAudit?.stimuli_count ?? groupedExtraction.stimuliGroups.length} Est • {qualityAudit?.media_assets_count ?? (docMedia?.confirmed?.length || 0)} Rec
                                        </div>
                                        <div style={{ fontSize: "0.72rem", color: (qualityAudit?.pending_media_count ?? 0) > 0 ? "#fbbf24" : "#94a3b8", marginTop: "2px" }}>
                                            {(qualityAudit?.pending_media_count ?? 0) > 0 ? `${qualityAudit.pending_media_count} recursos pendientes` : "Recursos asociados"}
                                        </div>
                                    </div>
                                    <div className="qst-extraction-kpi-icon" style={{ background: "rgba(249, 115, 22, 0.15)", color: "#f97316" }}>
                                        <i className="fa-solid fa-photo-film"></i>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* BANNER DE AUDITORÍA AUTOMÁTICA Y FILTROS DE CALIDAD */}
                        {selectedExtractionDocId && extractedQuestions.length > 0 && (
                            <div className="qst-audit-banner">
                                <div className="qst-audit-banner-header">
                                    <div className="qst-audit-banner-title">
                                        <i className="fa-solid fa-shield-halved" style={{ color: "#38bdf8" }}></i>
                                        <span>Auditoría Automática de Calidad Multimodal</span>
                                        {qualityAudit?.status === "completed" && (
                                            <span className="qst-audit-badge-ready" style={{ marginLeft: "8px" }}>
                                                <i className="fa-solid fa-circle-check"></i> Auditoría Superada
                                            </span>
                                        )}
                                        {(qualityAudit?.requires_review_count ?? auditCounts.issues) > 0 && (
                                            <span className="qst-audit-badge-review" style={{ marginLeft: "8px" }}>
                                                <i className="fa-solid fa-triangle-exclamation"></i> Requiere Atención ({qualityAudit?.requires_review_count ?? auditCounts.issues})
                                            </span>
                                        )}
                                    </div>

                                    <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
                                        <button
                                            type="button"
                                            className="qst-btn-outline"
                                            onClick={handleRunAudit}
                                            disabled={isRunningAudit || isExtracting}
                                            title="Ejecutar revalidación automática de calidad sobre las preguntas extraídas"
                                        >
                                            <i className={`fa-solid fa-arrows-rotate ${isRunningAudit ? "fa-spin" : ""}`}></i>
                                            <span>{isRunningAudit ? "Auditando..." : "Re-auditar Calidad"}</span>
                                        </button>
                                    </div>
                                </div>

                                {/* Fila de Filtros y Acción Rápida de Selección */}
                                <div className="qst-audit-filters-row">
                                    <div className="qst-audit-filter-group">
                                        <span style={{ fontSize: "0.8rem", color: "#94a3b8", fontWeight: 700 }}>
                                            Filtrar Vista:
                                        </span>
                                        <button
                                            type="button"
                                            className={`qst-audit-filter-btn ${auditFilter === "all" ? "qst-audit-filter-btn--active" : ""}`}
                                            onClick={() => setAuditFilter("all")}
                                        >
                                            <i className="fa-solid fa-list"></i>
                                            <span>Todas ({extractedQuestions.length})</span>
                                        </button>
                                        <button
                                            type="button"
                                            className={`qst-audit-filter-btn ${auditFilter === "ready" ? "qst-audit-filter-btn--active" : ""}`}
                                            onClick={() => setAuditFilter("ready")}
                                            style={auditFilter === "ready" ? { color: "#4ade80", borderColor: "#4ade80" } : {}}
                                        >
                                            <i className="fa-solid fa-circle-check" style={{ color: "#4ade80" }}></i>
                                            <span>Listas para Aprobación ({auditCounts.ready})</span>
                                        </button>
                                        <button
                                            type="button"
                                            className={`qst-audit-filter-btn ${auditFilter === "issues" ? "qst-audit-filter-btn--active" : ""}`}
                                            onClick={() => setAuditFilter("issues")}
                                            style={auditFilter === "issues" ? { color: "#fbbf24", borderColor: "#fbbf24" } : {}}
                                        >
                                            <i className="fa-solid fa-triangle-exclamation" style={{ color: "#fbbf24" }}></i>
                                            <span>Con Observaciones ({auditCounts.issues})</span>
                                        </button>
                                    </div>

                                    {auditCounts.ready > 0 && (
                                        <div>
                                            <button
                                                type="button"
                                                className="qst-btn-outline"
                                                onClick={handleSelectOnlyReady}
                                                style={{
                                                    background: "rgba(34, 197, 94, 0.12)",
                                                    borderColor: "rgba(34, 197, 94, 0.4)",
                                                    color: "#4ade80",
                                                    fontWeight: 700,
                                                    padding: "7px 14px",
                                                }}
                                                title="Selecciona automáticamente todas las preguntas que pasaron la validación sin observaciones"
                                            >
                                                <i className="fa-solid fa-check-double"></i>
                                                <span>Seleccionar {auditCounts.ready} listas para aprobación</span>
                                            </button>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}
                    </section>

                    {/* BARRA DE HERRAMIENTAS Y VISTAS DE PREGUNTAS */}
                    <div className="qst-questions-toolbar">
                        <div className="qst-questions-toolbar-title">
                            <i className="fa-solid fa-layer-group" style={{ color: "#22c55e" }}></i>
                            <span>
                                Reactivos Extraídos ({filteredExtractedQuestions.length}
                                {auditFilter !== "all" ? ` de ${extractedQuestions.length}` : ""})
                            </span>
                            {selectedExtractionDocObj && (
                                <span style={{ fontSize: "0.82rem", color: "#94a3b8", fontWeight: 400 }}>
                                    — {selectedExtractionDocObj.title}
                                </span>
                            )}
                        </div>
                        {extractedQuestions.length > 0 && (
                            <div className="qst-questions-toolbar-actions">
                                <button
                                    type="button"
                                    className="qst-btn-outline"
                                    onClick={expandAllQuestions}
                                    title="Expandir todas las opciones"
                                >
                                    <i className="fa-solid fa-angles-down"></i>
                                    <span>Expandir Opciones</span>
                                </button>
                                <button
                                    type="button"
                                    className="qst-btn-outline"
                                    onClick={collapseAllQuestions}
                                    title="Colapsar todas las opciones"
                                >
                                    <i className="fa-solid fa-angles-up"></i>
                                    <span>Colapsar Opciones</span>
                                </button>
                            </div>
                        )}
                    </div>

                    {/* CONTENIDO PRINCIPAL: PREGUNTAS AGRUPADAS Y ESTÍMULOS */}
                    {isLoadingExtractionData ? (
                        <div className="qst-ai-preview-card" style={{ padding: "40px" }}>
                            <span className="qst-spinner" style={{ width: "36px", height: "36px", borderWidth: "3px" }}></span>
                            <p style={{ color: "#94a3b8", fontSize: "0.95rem", margin: "10px 0 0 0" }}>
                                Cargando reactivos, opciones y estímulos desde Supabase...
                            </p>
                        </div>
                    ) : isExtracting ? (
                        <div className="qst-ai-preview-card" style={{ padding: "48px 32px" }}>
                            <div className="qst-ai-preview-icon" style={{ animation: "qst-spin 2s linear infinite" }}>
                                <i className="fa-solid fa-atom"></i>
                            </div>
                            <h3 style={{ fontSize: "1.2rem", color: "#ffffff", margin: 0 }}>
                                Gemini está procesando el cuadernillo PDF...
                            </h3>
                            <p style={{ color: "#94a3b8", fontSize: "0.88rem", maxWidth: "540px", lineHeight: 1.5, margin: 0 }}>
                                El microservicio está leyendo el PDF desde el bucket privado de Supabase Storage, extrayendo textos guía (estímulos), enunciados y opciones de respuesta. Este proceso toma unos segundos.
                            </p>
                        </div>
                    ) : extractedQuestions.length === 0 ? (
                        <div className="qst-ai-preview-card">
                            <div className="qst-ai-preview-icon" style={selectedExtractionDocObj?.status === "failed" ? { background: "rgba(239, 68, 68, 0.15)", color: "#ef4444" } : {}}>
                                <i className={selectedExtractionDocObj?.status === "failed" ? "fa-solid fa-triangle-exclamation" : "fa-solid fa-file-circle-question"}></i>
                            </div>
                            <h3 style={{ fontSize: "1.2rem", color: "#ffffff", margin: 0 }}>
                                {selectedExtractionDocObj?.status === "extracted"
                                    ? "No se encontraron preguntas en este documento"
                                    : selectedExtractionDocObj?.status === "failed"
                                    ? "La extracción previa reportó un error"
                                    : "Documento listo para extracción"}
                            </h3>
                            <p style={{ color: "#94a3b8", fontSize: "0.88rem", maxWidth: "520px", lineHeight: 1.5, margin: 0 }}>
                                {selectedExtractionDocObj?.status === "extracted"
                                    ? "La extracción finalizó pero el modelo no detectó preguntas válidas o el formato requiere calibración adicional."
                                    : selectedExtractionDocObj?.status === "failed"
                                    ? "El archivo PDF está almacenado de forma segura en Supabase Storage. Puedes reintentar la extracción directamente con el motor optimizado sin volver a subirlo."
                                    : "Haz clic en 'Extraer preguntas con Gemini' para iniciar el análisis automático y estructurar las preguntas oficiales del cuadernillo."}
                            </p>
                            {selectedExtractionDocObj?.status === "failed" && extractionStatusData?.extraction_metadata?.error_detail && (
                                <div style={{
                                    marginTop: "12px",
                                    padding: "10px 14px",
                                    backgroundColor: "rgba(239, 68, 68, 0.1)",
                                    border: "1px solid rgba(239, 68, 68, 0.25)",
                                    borderRadius: "8px",
                                    color: "#fca5a5",
                                    fontSize: "0.82rem",
                                    maxWidth: "560px",
                                    textAlign: "left",
                                    fontFamily: "monospace",
                                    wordBreak: "break-word",
                                }}>
                                    <strong>Detalle técnico:</strong> {extractionStatusData.extraction_metadata.error_detail}
                                </div>
                            )}
                            {selectedExtractionDocObj?.status !== "extracted" && selectedExtractionDocId && (
                                <button
                                    type="button"
                                    className="qst-btn-gemini-extract"
                                    onClick={handleTriggerExtraction}
                                    style={{ marginTop: "12px" }}
                                >
                                    <i className={selectedExtractionDocObj?.status === "failed" ? "fa-solid fa-rotate-right" : "fa-solid fa-bolt-lightning"}></i>
                                    <span>{selectedExtractionDocObj?.status === "failed" ? "Reintentar Extracción con Gemini" : "Iniciar Extracción Ahora"}</span>
                                </button>
                            )}
                        </div>
                    ) : (
                        <div style={{ display: "flex", flexDirection: "column", gap: "24px" }}>
                            <MediaAssociationPanel
                                docMedia={docMedia}
                                extractedQuestions={extractedQuestions}
                                onAssociationChanged={async () => {
                                    await Promise.all([
                                        fetchDocMedia(selectedExtractionDocId),
                                        loadExtractionData(selectedExtractionDocId),
                                        fetchBankQuestions(bankPage),
                                    ]);
                                }}
                            />

                            {/* BARRA DE APROBACIÓN MASIVA Y SELECCIÓN MÚLTIPLE (FASE 6) */}
                            <div
                                className="qst-bulk-approval-toolbar"
                                style={{
                                    display: "flex",
                                    flexWrap: "wrap",
                                    alignItems: "center",
                                    justifyContent: "space-between",
                                    gap: 12,
                                    padding: "14px 18px",
                                    borderRadius: 12,
                                    background: "rgba(15, 23, 42, 0.95)",
                                    border: "1px solid rgba(56, 189, 248, 0.35)",
                                    boxShadow: "0 6px 20px rgba(0, 0, 0, 0.4)",
                                }}
                            >
                                <div style={{ display: "flex", alignItems: "center", gap: 10, flexWrap: "wrap" }}>
                                    <span style={{ fontSize: "0.86rem", color: "#f8fafc", fontWeight: 700, display: "inline-flex", alignItems: "center", gap: 6 }}>
                                        <i className="fa-solid fa-list-check" style={{ color: "#38bdf8" }}></i>
                                        Selección Múltiple:
                                    </span>

                                    <button
                                        type="button"
                                        className="qst-btn-outline"
                                        onClick={handleSelectAll}
                                        style={{ fontSize: "0.78rem", padding: "5px 12px", background: "rgba(56, 189, 248, 0.08)" }}
                                        title="Marca todas las preguntas del documento para aprobación masiva"
                                    >
                                        <i className="fa-solid fa-check-double" style={{ color: "#38bdf8" }}></i>
                                        <span>Seleccionar todas ({extractedQuestions.length})</span>
                                    </button>

                                    <button
                                        type="button"
                                        className="qst-btn-outline"
                                        onClick={handleDeselectAll}
                                        style={{ fontSize: "0.78rem", padding: "5px 12px" }}
                                        title="Quita todas las marcas de selección"
                                    >
                                        <i className="fa-solid fa-xmark"></i>
                                        <span>Deseleccionar todas</span>
                                    </button>

                                    <button
                                        type="button"
                                        className="qst-btn-outline"
                                        onClick={handleSelectAllExceptExcluded}
                                        style={{ fontSize: "0.78rem", padding: "5px 12px" }}
                                        title="Invierte la selección actual para aprobar todas salvo las excluidas"
                                    >
                                        <i className="fa-solid fa-arrows-rotate"></i>
                                        <span>Seleccionar excepto excluidas</span>
                                    </button>
                                </div>

                                <div style={{ display: "flex", alignItems: "center", gap: 14 }}>
                                    <span
                                        style={{
                                            fontSize: "0.84rem",
                                            fontWeight: 700,
                                            color: selectedQuestionIds.size > 0 ? "#4ade80" : "#94a3b8",
                                            background: selectedQuestionIds.size > 0 ? "rgba(34, 197, 94, 0.12)" : "rgba(148, 163, 184, 0.1)",
                                            padding: "4px 10px",
                                            borderRadius: 6,
                                            border: selectedQuestionIds.size > 0 ? "1px solid rgba(34, 197, 94, 0.3)" : "1px solid rgba(148, 163, 184, 0.2)",
                                        }}
                                    >
                                        {selectedQuestionIds.size} de {extractedQuestions.length} seleccionadas
                                    </span>

                                    <button
                                        type="button"
                                        onClick={handleBulkApprove}
                                        disabled={selectedQuestionIds.size === 0 || isBulkApproving}
                                        style={{
                                            display: "inline-flex",
                                            alignItems: "center",
                                            gap: 8,
                                            padding: "9px 20px",
                                            background: selectedQuestionIds.size > 0 ? "linear-gradient(135deg, #16a34a 0%, #22c55e 100%)" : "rgba(148, 163, 184, 0.2)",
                                            color: selectedQuestionIds.size > 0 ? "#ffffff" : "#64748b",
                                            border: selectedQuestionIds.size > 0 ? "1px solid #4ade80" : "1px solid rgba(148, 163, 184, 0.3)",
                                            borderRadius: 8,
                                            fontWeight: 800,
                                            fontSize: "0.86rem",
                                            cursor: selectedQuestionIds.size > 0 && !isBulkApproving ? "pointer" : "not-allowed",
                                            boxShadow: selectedQuestionIds.size > 0 ? "0 4px 16px rgba(34, 197, 94, 0.4)" : "none",
                                            transition: "all 0.2s ease",
                                        }}
                                        title={selectedQuestionIds.size === 0 ? "Selecciona una o más preguntas para aprobar" : "Aprobar todas las preguntas seleccionadas para el banco"}
                                    >
                                        {isBulkApproving ? (
                                            <>
                                                <span className="qst-spinner" style={{ width: 14, height: 14, borderWidth: 2 }}></span>
                                                <span>Aprobando masivamente...</span>
                                            </>
                                        ) : (
                                            <>
                                                <i className="fa-solid fa-circle-check"></i>
                                                <span>APROBAR TODAS LAS SELECCIONADAS ({selectedQuestionIds.size})</span>
                                            </>
                                        )}
                                    </button>
                                </div>
                            </div>

                            {/* 1. SECCIÓN DE ESTÍMULOS COMPARTIDOS */}
                            {groupedExtraction.stimuliGroups.map((group, groupIdx) => {
                                const stim = group.stimulus;
                                return (
                                    <div key={stim.id || groupIdx} className="qst-stimulus-container">
                                        <div className="qst-stimulus-header">
                                            <div className="qst-stimulus-title-box">
                                                <i className="fa-solid fa-file-lines"></i>
                                                <div>
                                                    <h3 className="qst-stimulus-title">
                                                        {stim.title || `Estímulo de Lectura / Contexto Compartido #${groupIdx + 1}`}
                                                    </h3>
                                                    <span style={{ fontSize: "0.75rem", color: "#94a3b8" }}>
                                                        ID: {stim.id}
                                                    </span>
                                                </div>
                                            </div>
                                            <span className="qst-stimulus-badge-count">
                                                <i className="fa-solid fa-link"></i>
                                                {group.questions.length} preguntas asociadas (Reactivos: {group.questions.map(q => q.order_in_group || "?").join(", ")})
                                            </span>
                                        </div>

                                        {stim.instructions && (
                                            <div className="qst-stimulus-instructions">
                                                <i className="fa-solid fa-circle-info"></i>
                                                <span>{stim.instructions}</span>
                                            </div>
                                        )}

                                        {stim.content_text && (
                                            <div className="qst-stimulus-content-box">
                                                {stim.content_text}
                                            </div>
                                        )}

                                        <div style={{ padding: "0 4px 8px" }}>
                                            <StimulusMedia stimulus={stim} />
                                        </div>

                                        <div className="qst-stimulus-questions-list">
                                            {group.questions.map((q) => (
                                                <QuestionCardItem
                                                    key={q.id}
                                                    q={q}
                                                    isExpanded={expandedQuestionIds.has(q.id)}
                                                    onToggleExpand={() => toggleQuestionExpanded(q.id)}
                                                    onEdit={handleOpenEdit}
                                                    onApprove={handleApprove}
                                                    onReject={handlePromptReject}
                                                    isProcessingAction={actionLoadingId === q.id}
                                                    showCheckbox={true}
                                                    isSelected={selectedQuestionIds.has(q.id)}
                                                    onToggleSelect={handleToggleSelectQuestion}
                                                />
                                            ))}
                                        </div>
                                    </div>
                                );
                            })}

                            {/* 2. SECCIÓN DE PREGUNTAS INDEPENDIENTES */}
                            {groupedExtraction.standaloneQuestions.length > 0 && (
                                <div className="qst-standalone-wrapper">
                                    <div className="qst-standalone-header">
                                        <i className="fa-solid fa-circle-question"></i>
                                        <span>
                                            Preguntas Independientes (Sin estímulo compartido) — {groupedExtraction.standaloneQuestions.length} reactivos
                                        </span>
                                    </div>
                                    {groupedExtraction.standaloneQuestions.map((q) => (
                                        <QuestionCardItem
                                            key={q.id}
                                            q={q}
                                            isExpanded={expandedQuestionIds.has(q.id)}
                                            onToggleExpand={() => toggleQuestionExpanded(q.id)}
                                            onEdit={handleOpenEdit}
                                            onApprove={handleApprove}
                                            onReject={handlePromptReject}
                                            isProcessingAction={actionLoadingId === q.id}
                                            showCheckbox={true}
                                            isSelected={selectedQuestionIds.has(q.id)}
                                            onToggleSelect={handleToggleSelectQuestion}
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
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

            {/* =========================================================
                MODAL 5: EDICIÓN Y REVISIÓN PEDAGÓGICA (FASE 2.9)
                ========================================================= */}
            {isEditModalOpen && editingQuestion && (
                <div className="qst-modal-overlay" onClick={() => !isSavingEdit && setIsEditModalOpen(false)}>
                    <div className="qst-modal-card" style={{ maxWidth: "780px" }} onClick={(e) => e.stopPropagation()}>
                        <div className="qst-modal-header">
                            <div>
                                <h2 className="qst-modal-title">
                                    <i className="fa-solid fa-pen-ruler"></i>
                                    Revisión y Edición Pedagógica de Reactivo
                                </h2>
                                <span style={{ fontSize: "0.78rem", color: "#94a3b8" }}>
                                    Pregunta #{editingQuestion.order_in_group || editingQuestion.id.slice(0, 8)} • ID: {editingQuestion.id}
                                </span>
                            </div>
                            <button
                                className="qst-modal-close-btn"
                                onClick={() => !isSavingEdit && setIsEditModalOpen(false)}
                                disabled={isSavingEdit}
                                aria-label="Cerrar edición"
                            >
                                <i className="fa-solid fa-xmark"></i>
                            </button>
                        </div>

                        <form onSubmit={handleSaveEdit}>
                            <div className="qst-modal-body" style={{ maxHeight: "72vh", overflowY: "auto" }}>
                                {/* Notificación si tiene estímulo compartido */}
                                {editingQuestion.stimulus_id && (
                                    <div style={{ background: "rgba(245, 158, 11, 0.08)", border: "1px dashed rgba(245, 158, 11, 0.35)", borderRadius: "8px", padding: "10px 14px", fontSize: "0.82rem", color: "#fbbf24", display: "flex", alignItems: "center", gap: "10px" }}>
                                        <i className="fa-solid fa-link" style={{ fontSize: "1.1rem" }}></i>
                                        <span>
                                            <strong>Reactivo vinculado a estímulo compartido:</strong> La materia académica está fijada por el texto de lectura fuente para salvaguardar la coherencia estructural de la prueba.
                                        </span>
                                    </div>
                                )}

                                {/* Materia, Tema y Dificultad */}
                                <div className="qst-form-grid-3">
                                    <div className="qst-form-group">
                                        <label className="qst-form-label">Área Académica ICFES *</label>
                                        <select
                                            className="qst-form-select"
                                            value={editForm.subject_id}
                                            onChange={(e) => handleEditSubjectChange(e.target.value)}
                                            disabled={isSavingEdit || Boolean(editingQuestion.stimulus_id)}
                                        >
                                            <option value={1}>Lectura Crítica</option>
                                            <option value={2}>Matemáticas</option>
                                            <option value={3}>Ciencias Naturales</option>
                                            <option value={4}>Sociales y Ciudadanas</option>
                                            <option value={5}>Inglés</option>
                                        </select>
                                    </div>

                                    <div className="qst-form-group">
                                        <label className="qst-form-label">
                                            Tema Curricular {isLoadingTopics && <i className="fa-solid fa-spinner fa-spin"></i>}
                                        </label>
                                        <select
                                            className="qst-form-select"
                                            value={editForm.topic_id}
                                            onChange={(e) => setEditForm({ ...editForm, topic_id: e.target.value })}
                                            disabled={isSavingEdit || availableTopics.length === 0}
                                        >
                                            {availableTopics.length === 0 ? (
                                                <option value="">Sin temas en catálogo (pendiente)</option>
                                            ) : (
                                                <>
                                                    <option value="">-- Seleccionar tema --</option>
                                                    {availableTopics.map((t) => (
                                                        <option key={t.id} value={t.id}>
                                                            {t.name}
                                                        </option>
                                                    ))}
                                                </>
                                            )}
                                        </select>
                                    </div>

                                    <div className="qst-form-group">
                                        <label className="qst-form-label">Nivel de Dificultad</label>
                                        <select
                                            className="qst-form-select"
                                            value={editForm.difficulty}
                                            onChange={(e) => setEditForm({ ...editForm, difficulty: e.target.value })}
                                            disabled={isSavingEdit}
                                        >
                                            <option value="easy">Fácil (easy)</option>
                                            <option value="medium">Media (medium)</option>
                                            <option value="hard">Difícil (hard)</option>
                                        </select>
                                    </div>
                                </div>

                                {/* Enunciado de la Pregunta */}
                                <div className="qst-form-group">
                                    <label className="qst-form-label">Enunciado del Reactivo *</label>
                                    <textarea
                                        rows="4"
                                        className="qst-form-textarea"
                                        placeholder="Redacta o corrige el enunciado oficial de la pregunta..."
                                        value={editForm.statement}
                                        onChange={(e) => setEditForm({ ...editForm, statement: e.target.value })}
                                        required
                                        disabled={isSavingEdit}
                                    ></textarea>
                                </div>

                                {/* Opciones de Respuesta A, B, C y D */}
                                <div className="qst-options-container" style={{ marginTop: "4px" }}>
                                    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "12px", flexWrap: "wrap", gap: "8px" }}>
                                        <h3 className="qst-options-heading" style={{ margin: 0 }}>
                                            <i className="fa-solid fa-list-check"></i> Opciones de Respuesta & Clave
                                        </h3>
                                        <span style={{ fontSize: "0.78rem", color: "#94a3b8" }}>
                                            Haz clic en la letra de la opción para marcarla como la respuesta correcta
                                        </span>
                                    </div>

                                    <div style={{ display: "flex", flexDirection: "column", gap: "10px" }}>
                                        {["A", "B", "C", "D"].map((letter) => {
                                            const isSelectedCorrect = editForm.correct_option === letter;
                                            return (
                                                <div
                                                    key={letter}
                                                    className={`qst-edit-option-row ${isSelectedCorrect ? "qst-edit-option-row--correct" : ""}`}
                                                >
                                                    <button
                                                        type="button"
                                                        className={`qst-correct-radio-label ${isSelectedCorrect ? "qst-correct-radio-label--active" : ""}`}
                                                        onClick={() => setEditForm({ ...editForm, correct_option: letter })}
                                                        title="Marcar como respuesta correcta"
                                                    >
                                                        <i className={isSelectedCorrect ? "fa-solid fa-circle-dot" : "fa-regular fa-circle"}></i>
                                                        <span>Opción {letter}</span>
                                                    </button>

                                                    <input
                                                        type="text"
                                                        className="qst-form-input"
                                                        style={{ flex: 1 }}
                                                        placeholder={`Texto de la opción ${letter}...`}
                                                        value={editForm.options[letter] || ""}
                                                        onChange={(e) =>
                                                            setEditForm({
                                                                ...editForm,
                                                                options: {
                                                                    ...editForm.options,
                                                                    [letter]: e.target.value,
                                                                },
                                                            })
                                                        }
                                                        required
                                                        disabled={isSavingEdit}
                                                    />

                                                    {isSelectedCorrect && (
                                                        <span className="qst-correct-pill-label" style={{ alignSelf: "center", whiteSpace: "nowrap" }}>
                                                            <i className="fa-solid fa-check"></i> Correcta
                                                        </span>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>

                                {/* Switch / Card de Verificación de Clave */}
                                <div className="qst-key-toggle-card">
                                    <div>
                                        <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                                            <span style={{ fontWeight: 700, color: "#f8fafc", fontSize: "0.9rem" }}>
                                                Validación Pedagógica de la Clave
                                            </span>
                                            {editForm.key_status === "verified" ? (
                                                <span className="qst-key-badge qst-key-badge--verified">
                                                    <i className="fa-solid fa-circle-check"></i> Clave Verificada
                                                </span>
                                            ) : (
                                                <span className="qst-key-badge qst-key-badge--unverified">
                                                    <i className="fa-solid fa-triangle-exclamation"></i> Clave Pendiente
                                                </span>
                                            )}
                                        </div>
                                        <p style={{ color: "#94a3b8", fontSize: "0.78rem", margin: "4px 0 0 0" }}>
                                            {editForm.key_status === "verified"
                                                ? `La opción ${editForm.correct_option} está certificada como la respuesta correcta para el examen.`
                                                : "Marque esta casilla si comprobó la respuesta correcta en la hoja de respuestas oficial o por calibración docente."}
                                        </p>
                                    </div>

                                    <label style={{ display: "flex", alignItems: "center", gap: "8px", cursor: "pointer" }}>
                                        <input
                                            type="checkbox"
                                            checked={editForm.key_status === "verified"}
                                            onChange={(e) =>
                                                setEditForm({
                                                    ...editForm,
                                                    key_status: e.target.checked ? "verified" : "unverified",
                                                })
                                            }
                                            disabled={isSavingEdit}
                                            style={{ width: "20px", height: "20px", accentColor: "#22c55e", cursor: "pointer" }}
                                        />
                                        <span style={{ fontSize: "0.85rem", fontWeight: 600, color: "#f8fafc" }}>
                                            Clave Verificada
                                        </span>
                                    </label>
                                </div>

                                {/* Justificación Pedagógica */}
                                <div className="qst-form-group">
                                    <label className="qst-form-label">
                                        Explicación o Justificación Pedagógica (Opcional)
                                    </label>
                                    <textarea
                                        rows="2"
                                        className="qst-form-textarea"
                                        placeholder="Argumentación técnica del por qué la opción seleccionada es la correcta..."
                                        value={editForm.explanation}
                                        onChange={(e) => setEditForm({ ...editForm, explanation: e.target.value })}
                                        disabled={isSavingEdit}
                                    ></textarea>
                                </div>
                            </div>

                            <div className="qst-modal-footer">
                                <button
                                    type="button"
                                    className="qst-btn-cancel"
                                    onClick={() => setIsEditModalOpen(false)}
                                    disabled={isSavingEdit}
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    className="qst-btn-save"
                                    disabled={isSavingEdit}
                                >
                                    {isSavingEdit ? (
                                        <>
                                            <span className="qst-spinner" style={{ width: "14px", height: "14px" }}></span>
                                            <span>Guardando cambios...</span>
                                        </>
                                    ) : (
                                        <>
                                            <i className="fa-solid fa-floppy-disk"></i>
                                            <span>Guardar Cambios</span>
                                        </>
                                    )}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* =========================================================
                MODAL 6: CONFIRMAR RECHAZO DE REACTIVO (FASE 2.9)
                ========================================================= */}
            {isConfirmRejectOpen && questionToReject && (
                <div className="qst-modal-overlay" onClick={() => !actionLoadingId && setIsConfirmRejectOpen(false)}>
                    <div className="qst-modal-card" style={{ maxWidth: "460px" }} onClick={(e) => e.stopPropagation()}>
                        <div className="qst-modal-header">
                            <h2 className="qst-modal-title" style={{ color: "#ef4444" }}>
                                <i className="fa-solid fa-triangle-exclamation"></i>
                                Confirmar Rechazo de Reactivo
                            </h2>
                            <button
                                className="qst-modal-close-btn"
                                onClick={() => !actionLoadingId && setIsConfirmRejectOpen(false)}
                                disabled={Boolean(actionLoadingId)}
                                aria-label="Cerrar modal"
                            >
                                <i className="fa-solid fa-xmark"></i>
                            </button>
                        </div>

                        <div className="qst-modal-body">
                            <p style={{ color: "#f8fafc", fontSize: "0.92rem", margin: "0 0 10px 0" }}>
                                ¿Deseas rechazar y archivar este reactivo extraído?
                            </p>
                            <div style={{ background: "rgba(10, 17, 40, 0.7)", padding: "12px 14px", borderRadius: "8px", border: "1px solid var(--qst-border)", marginBottom: "12px" }}>
                                <div style={{ fontWeight: 700, color: "#ffffff", marginBottom: "4px" }}>
                                    Pregunta #{questionToReject.order_in_group || questionToReject.id.slice(0, 8)}
                                </div>
                                <div style={{ fontSize: "0.82rem", color: "#94a3b8", display: "-webkit-box", WebkitLineClamp: 2, WebkitBoxOrient: "vertical", overflow: "hidden" }}>
                                    {questionToReject.statement}
                                </div>
                            </div>
                            <p style={{ color: "#94a3b8", fontSize: "0.82rem", margin: 0 }}>
                                <i className="fa-solid fa-shield-halved" style={{ color: "#22c55e" }}></i> El reactivo cambiará a estado <strong>Archivada</strong>. Los textos de estímulo compartidos y demás preguntas del cuadernillo permanecerán totalmente intactos.
                            </p>
                        </div>

                        <div className="qst-modal-footer">
                            <button
                                type="button"
                                className="qst-btn-cancel"
                                onClick={() => setIsConfirmRejectOpen(false)}
                                disabled={Boolean(actionLoadingId)}
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                className="qst-btn-save"
                                style={{ background: "linear-gradient(135deg, #ef4444 0%, #dc2626 100%)", boxShadow: "0 4px 12px rgba(239, 68, 68, 0.3)" }}
                                onClick={handleConfirmReject}
                                disabled={Boolean(actionLoadingId)}
                            >
                                {actionLoadingId ? (
                                    <>
                                        <span className="qst-spinner" style={{ width: "14px", height: "14px" }}></span>
                                        <span>Archivando...</span>
                                    </>
                                ) : (
                                    <>
                                        <i className="fa-solid fa-ban"></i>
                                        <span>Confirmar Rechazo</span>
                                    </>
                                )}
                            </button>
                        </div>
                    </div>
                </div>
            )}
            {/* Modal de Resumen de Aprobación Masiva */}
            {isBulkSummaryOpen && bulkSummary && (
                <div className="qst-modal-overlay" onClick={() => setIsBulkSummaryOpen(false)}>
                    <div
                        className="qst-modal-card"
                        onClick={(e) => e.stopPropagation()}
                        style={{ maxWidth: 640 }}
                    >
                        <div className="qst-modal-header">
                            <div className="qst-modal-title">
                                <i className="fa-solid fa-list-check" style={{ color: "#22c55e", marginRight: 8 }}></i>
                                <span>Reporte de Aprobación Masiva</span>
                            </div>
                            <button
                                type="button"
                                className="qst-modal-close-btn"
                                onClick={() => setIsBulkSummaryOpen(false)}
                            >
                                <i className="fa-solid fa-xmark"></i>
                            </button>
                        </div>

                        <div className="qst-modal-body" style={{ display: "flex", flexDirection: "column", gap: 16 }}>
                            <div style={{
                                display: "grid",
                                gridTemplateColumns: "repeat(3, 1fr)",
                                gap: 12,
                                textAlign: "center",
                            }}>
                                <div style={{ background: "rgba(15, 23, 42, 0.6)", padding: "12px", borderRadius: 8, border: "1px solid rgba(148, 163, 184, 0.2)" }}>
                                    <div style={{ fontSize: "1.4rem", fontWeight: 800, color: "#f8fafc" }}>{bulkSummary.total_received}</div>
                                    <div style={{ fontSize: "0.75rem", color: "#94a3b8" }}>Total Evaluadas</div>
                                </div>
                                <div style={{ background: "rgba(34, 197, 94, 0.1)", padding: "12px", borderRadius: 8, border: "1px solid rgba(34, 197, 94, 0.3)" }}>
                                    <div style={{ fontSize: "1.4rem", fontWeight: 800, color: "#4ade80" }}>{bulkSummary.total_approved}</div>
                                    <div style={{ fontSize: "0.75rem", color: "#86efac" }}>Aprobadas</div>
                                </div>
                                <div style={{ background: "rgba(239, 68, 68, 0.1)", padding: "12px", borderRadius: 8, border: "1px solid rgba(239, 68, 68, 0.3)" }}>
                                    <div style={{ fontSize: "1.4rem", fontWeight: 800, color: "#f87171" }}>{bulkSummary.total_rejected}</div>
                                    <div style={{ fontSize: "0.75rem", color: "#fca5a5" }}>Rechazadas</div>
                                </div>
                            </div>

                            {bulkSummary.total_rejected > 0 && bulkSummary.rejected && (
                                <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
                                    <h4 style={{ color: "#fca5a5", fontSize: "0.88rem", margin: "4px 0 0 0", display: "flex", alignItems: "center", gap: 6 }}>
                                        <i className="fa-solid fa-triangle-exclamation"></i>
                                        Detalle de reactivos que no pudieron ser aprobados:
                                    </h4>
                                    <div style={{ maxHeight: 240, overflowY: "auto", display: "flex", flexDirection: "column", gap: 6 }}>
                                        {bulkSummary.rejected.map((rej, idx) => (
                                            <div
                                                key={rej.id || idx}
                                                style={{
                                                    padding: "8px 12px",
                                                    borderRadius: 6,
                                                    background: "rgba(239, 68, 68, 0.08)",
                                                    border: "1px solid rgba(239, 68, 68, 0.25)",
                                                    fontSize: "0.8rem",
                                                    color: "#fecaca",
                                                    display: "flex",
                                                    justifyContent: "space-between",
                                                    alignItems: "center",
                                                }}
                                            >
                                                <span>
                                                    <strong>Pregunta #{rej.order || "—"}:</strong> {rej.reason}
                                                </span>
                                                <span style={{ fontSize: "0.72rem", color: "#94a3b8", fontFamily: "monospace" }}>
                                                    {rej.id ? rej.id.slice(0, 8) : ""}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {bulkSummary.total_approved > 0 && (
                                <div style={{
                                    padding: "10px 14px",
                                    borderRadius: 8,
                                    background: "rgba(34, 197, 94, 0.08)",
                                    border: "1px solid rgba(34, 197, 94, 0.2)",
                                    color: "#86efac",
                                    fontSize: "0.82rem",
                                }}>
                                    <i className="fa-solid fa-circle-check" style={{ marginRight: 6 }}></i>
                                    Las preguntas aprobadas han pasado al banco oficial con estado <strong>review</strong> listas para calibración pedagógica.
                                </div>
                            )}
                        </div>

                        <div className="qst-modal-footer">
                            <button
                                type="button"
                                className="qst-btn-save"
                                style={{ background: "#3b82f6" }}
                                onClick={() => setIsBulkSummaryOpen(false)}
                            >
                                Entendido
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default Questions;
