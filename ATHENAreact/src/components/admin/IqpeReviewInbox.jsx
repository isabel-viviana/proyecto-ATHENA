import { useState, useEffect, useCallback } from "react";
import { getIqpeReviewQueue } from "../../api/documents";
import { MediaImage, StimulusMedia, QuestionMedia } from "./QuestionMedia";

const FILTER_TABS = [
    { key: "all", label: "Todas", icon: "fa-layer-group", color: "#94a3b8" },
    { key: "ready_for_approval", label: "Listas para aprobación", icon: "fa-circle-check", color: "#4ade80" },
    { key: "requires_review", label: "Requieren revisión", icon: "fa-triangle-exclamation", color: "#fbbf24" },
    { key: "with_official_key", label: "Con clave oficial", icon: "fa-key", color: "#34d399" },
    { key: "without_official_key", label: "Sin clave oficial", icon: "fa-circle-question", color: "#f59e0b" },
    { key: "discrepancies", label: "Con discrepancias", icon: "fa-code-compare", color: "#f97316" },
    { key: "quality_issues", label: "Problemas de calidad", icon: "fa-shield-halved", color: "#f87171" },
    { key: "duplicates", label: "Posibles duplicados", icon: "fa-clone", color: "#e879f9" },
    { key: "approved", label: "Ya aprobadas", icon: "fa-check-double", color: "#38bdf8" },
    { key: "rejected", label: "Rechazadas", icon: "fa-ban", color: "#ef4444" },
    { key: "published", label: "Publicadas", icon: "fa-globe", color: "#c084fc" },
];

const SUBJECT_NAMES = {
    1: "Lectura Crítica",
    2: "Matemáticas",
    3: "Ciencias Naturales",
    4: "Sociales y Ciudadanas",
    5: "Inglés",
};

const IqpeReviewInbox = ({
    documents = [],
    refreshNonce = 0,
    onOpenDetail,
    onEdit,
    onRequestApprove,
    onRequestReject,
    onRequestReturnToReview,
    onRequestPublish,
}) => {
    const [activeFilter, setActiveFilter] = useState("all");
    const [subjectFilter, setSubjectFilter] = useState("Todas");
    const [docFilter, setDocFilter] = useState("Todos");
    const [searchText, setSearchText] = useState("");
    const [page, setPage] = useState(1);
    const [perPage] = useState(10);

    const [items, setItems] = useState([]);
    const [counts, setCounts] = useState({});
    const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0, from: null, to: null });
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState(null);

    const fetchQueue = useCallback(async (targetPage = 1) => {
        setIsLoading(true);
        setError(null);
        try {
            const params = {
                filter: activeFilter,
                page: targetPage,
                per_page: perPage,
            };
            if (subjectFilter !== "Todas") params.subject_id = subjectFilter;
            if (docFilter !== "Todos") params.source_document_id = docFilter;
            if (searchText.trim()) params.search = searchText.trim();

            const res = await getIqpeReviewQueue(params);
            if (res?.success) {
                setItems(res.data || []);
                setCounts(res.filter_counts || {});
                setMeta(res.meta || { current_page: targetPage, last_page: 1, total: 0 });
                setPage(res.meta?.current_page || targetPage);
            } else {
                setError(res?.message || "No se pudo consultar la bandeja de revisión IQPE.");
            }
        } catch (err) {
            setError(err?.response?.data?.message || err.message || "Error consultando bandeja de revisión IQPE.");
        } finally {
            setIsLoading(false);
        }
    }, [activeFilter, subjectFilter, docFilter, searchText, perPage]);

    useEffect(() => {
        fetchQueue(1);
    }, [activeFilter, subjectFilter, docFilter, refreshNonce, fetchQueue]);

    return (
        <div style={{ display: "flex", flexDirection: "column", gap: "18px" }}>
            {/* 1. CHIPS DE LOS 10 FILTROS ESPECIALIZADOS IQPE */}
            <section className="qst-audit-banner" style={{ marginTop: 0 }}>
                <div className="qst-audit-banner-header">
                    <div className="qst-audit-banner-title">
                        <i className="fa-solid fa-clipboard-check" style={{ color: "#22c55e" }}></i>
                        <span>Bandeja de Revisión, Aprobación y Publicación IQPE</span>
                    </div>
                    <button
                        type="button"
                        className="qst-btn-outline"
                        onClick={() => fetchQueue(page)}
                        disabled={isLoading}
                    >
                        <i className={`fa-solid fa-arrows-rotate ${isLoading ? "fa-spin" : ""}`}></i>
                        <span>Actualizar Bandeja</span>
                    </button>
                </div>

                <div style={{ display: "flex", flexWrap: "wrap", gap: "8px" }}>
                    {FILTER_TABS.map((tab) => {
                        const isActive = activeFilter === tab.key;
                        const totalCount = counts[tab.key] ?? 0;
                        return (
                            <button
                                key={tab.key}
                                type="button"
                                className={`qst-audit-filter-btn ${isActive ? "qst-audit-filter-btn--active" : ""}`}
                                style={isActive ? { borderColor: tab.color, color: tab.color, background: "rgba(15, 23, 42, 0.9)" } : {}}
                                onClick={() => {
                                    setActiveFilter(tab.key);
                                    setPage(1);
                                }}
                            >
                                <i className={`fa-solid ${tab.icon}`} style={{ color: tab.color }}></i>
                                <span>{tab.label}</span>
                                <span style={{ fontSize: "0.72rem", background: "rgba(148,163,184,0.16)", padding: "1px 6px", borderRadius: "999px", color: "#f8fafc" }}>
                                    {totalCount}
                                </span>
                            </button>
                        );
                    })}
                </div>
            </section>

            {/* 2. BARRA DE BÚSQUEDA Y FILTROS SECUNDARIOS */}
            <section className="qst-tools-bar">
                <div className="qst-search-box">
                    <i className="fa-solid fa-magnifying-glass qst-search-icon"></i>
                    <input
                        type="text"
                        placeholder="Buscar en enunciado, materia, tema o cuadernillo..."
                        className="qst-search-input"
                        value={searchText}
                        onChange={(e) => setSearchText(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === "Enter") fetchQueue(1);
                        }}
                    />
                </div>

                <div className="qst-filters-group">
                    <div className="qst-select-wrap">
                        <label className="qst-select-label" htmlFor="iqpe-filter-subject">Materia:</label>
                        <select
                            id="iqpe-filter-subject"
                            className="qst-select"
                            value={subjectFilter}
                            onChange={(e) => {
                                setSubjectFilter(e.target.value);
                                setPage(1);
                            }}
                        >
                            <option value="Todas">Todas</option>
                            <option value="1">Lectura Crítica</option>
                            <option value="2">Matemáticas</option>
                            <option value="3">Ciencias Naturales</option>
                            <option value="4">Sociales y Ciudadanas</option>
                            <option value="5">Inglés</option>
                        </select>
                    </div>

                    <div className="qst-select-wrap">
                        <label className="qst-select-label" htmlFor="iqpe-filter-doc">Cuadernillo:</label>
                        <select
                            id="iqpe-filter-doc"
                            className="qst-select"
                            value={docFilter}
                            onChange={(e) => {
                                setDocFilter(e.target.value);
                                setPage(1);
                            }}
                        >
                            <option value="Todos">Todos los documentos</option>
                            {documents.map((doc) => (
                                <option key={doc.id} value={doc.id}>
                                    {doc.title}
                                </option>
                            ))}
                        </select>
                    </div>

                    <button type="button" className="qst-btn-outline" onClick={() => fetchQueue(1)}>
                        <i className="fa-solid fa-filter"></i> Aplicar
                    </button>
                </div>
            </section>

            {error && (
                <div className="qst-alert qst-alert--error">
                    <span>{error}</span>
                </div>
            )}

            {/* 3. LISTADO DE PREGUNTAS DE LA BANDEJA */}
            <section className="qst-table-card" style={{ padding: "16px" }}>
                {isLoading ? (
                    <div style={{ textAlign: "center", padding: "48px 20px" }}>
                        <span className="qst-spinner" style={{ width: "34px", height: "34px", borderWidth: "3px" }}></span>
                        <p style={{ color: "#94a3b8", marginTop: "12px" }}>Cargando preguntas, resoluciones y auditorías...</p>
                    </div>
                ) : items.length === 0 ? (
                    <div className="qst-empty-state" style={{ padding: "44px 20px" }}>
                        <i className="fa-solid fa-inbox" style={{ fontSize: "2.2rem", color: "#64748b" }}></i>
                        <h3 style={{ color: "#ffffff", margin: "10px 0 4px" }}>No hay preguntas en este filtro</h3>
                        <p style={{ color: "#94a3b8", fontSize: "0.85rem", margin: 0 }}>
                            Cambia el filtro superior o procesa un cuadernillo PDF en la pestaña de Cuadernillos / Extracción IA.
                        </p>
                    </div>
                ) : (
                    <div style={{ display: "flex", flexDirection: "column", gap: "16px" }}>
                        {items.map((q) => {
                            const iqpe = q.iqpe_summary || {};
                            const res = iqpe.resolutions_summary || {};
                            const ver = iqpe.verification_status;
                            const audit = iqpe.quality_audit_status;
                            const dup = iqpe.duplicate_status || {};
                            const eligibility = iqpe.eligibility || {};
                            const officialKey = iqpe.official_key || {};

                            const subjectName = q.subject?.name || SUBJECT_NAMES[q.subject_id] || "ICFES";
                            const hasMedia =
                                Boolean(q.question_image_path) ||
                                (q.confirmed_media_assets?.length || 0) > 0 ||
                                Boolean(q.stimulus?.media_path) ||
                                (q.stimulus?.confirmed_media_assets?.length || 0) > 0;

                            return (
                                <article key={q.id} className="qst-question-item-card">
                                    {/* Cabecera con estado de vida, materia, tema y badges IQPE */}
                                    <div className="qst-question-card-top">
                                        <div style={{ display: "flex", alignItems: "center", gap: "8px", flexWrap: "wrap" }}>
                                            <span className="qst-question-num-tag">#{q.order_in_group || q.id?.slice(0, 8)}</span>
                                            <span className="qst-iqpe-pill qst-iqpe-pill--blue">{subjectName}</span>
                                            {q.topic?.name && (
                                                <span className="qst-iqpe-pill qst-iqpe-pill--muted">
                                                    <i className="fa-solid fa-tag"></i> {q.topic.name}
                                                </span>
                                            )}
                                            <span className={`qst-iqpe-pill ${
                                                q.status === "published"
                                                    ? "qst-iqpe-pill--purple"
                                                    : q.status === "review"
                                                    ? "qst-iqpe-pill--blue"
                                                    : q.status === "archived"
                                                    ? "qst-iqpe-pill--red"
                                                    : "qst-iqpe-pill--amber"
                                            }`}>
                                                Estado: {q.status === "review" ? "Aprobada (review)" : q.status}
                                            </span>
                                            {hasMedia && (
                                                <span className="qst-iqpe-pill qst-iqpe-pill--blue">
                                                    <i className="fa-solid fa-image"></i> Visual
                                                </span>
                                            )}
                                        </div>

                                        <div style={{ display: "flex", alignItems: "center", gap: "6px", flexWrap: "wrap" }}>
                                            {iqpe.candidate_answer && (
                                                <span className="qst-iqpe-pill qst-iqpe-pill--green">
                                                    Candidata: {iqpe.candidate_answer}
                                                </span>
                                            )}
                                            {officialKey.manual_key_assigned && (
                                                <span className="qst-iqpe-pill qst-iqpe-pill--amber" title="Clave asignada manualmente">
                                                    Clave Manual
                                                </span>
                                            )}
                                            {ver && (
                                                <span className={`qst-iqpe-pill ${ver.requires_admin_review ? "qst-iqpe-pill--amber" : "qst-iqpe-pill--green"}`}>
                                                    Verificación: {ver.verdict}
                                                </span>
                                            )}
                                            {audit && (
                                                <span className={`qst-iqpe-pill ${audit.passed_quality_check ? "qst-iqpe-pill--green" : "qst-iqpe-pill--red"}`}>
                                                    Calidad: {audit.overall_classification}
                                                </span>
                                            )}
                                            {dup.duplicate_risk_flag && (
                                                <span className="qst-iqpe-pill qst-iqpe-pill--red">
                                                    <i className="fa-solid fa-clone"></i> {dup.classification}
                                                </span>
                                            )}
                                        </div>
                                    </div>

                                    {/* Estímulo relacionado si existe */}
                                    {q.stimulus && (
                                        <div style={{ background: "rgba(15, 23, 42, 0.65)", borderLeft: "3px solid #38bdf8", padding: "8px 12px", borderRadius: "6px", fontSize: "0.8rem", color: "#cbd5e1" }}>
                                            <strong style={{ color: "#38bdf8" }}>
                                                <i className="fa-solid fa-book-open-reader"></i> Estímulo relacionado: {q.stimulus.title || "Lectura compartida"}
                                            </strong>
                                            {q.stimulus.content_text && (
                                                <div style={{ marginTop: "4px", color: "#94a3b8", display: "-webkit-box", WebkitLineClamp: 2, WebkitBoxOrient: "vertical", overflow: "hidden" }}>
                                                    {q.stimulus.content_text}
                                                </div>
                                            )}
                                            <StimulusMedia stimulus={q.stimulus} />
                                        </div>
                                    )}

                                    {/* Enunciado y recursos visuales */}
                                    <div className="qst-question-statement-text">{q.statement}</div>
                                    <QuestionMedia question={q} />

                                    {/* Opciones A, B, C, D */}
                                    <div className="qst-options-list-grid">
                                        {(q.options || []).map((opt) => {
                                            const isCorrect = q.key_status === "verified" && opt.is_correct === true;
                                            return (
                                                <div key={opt.id || opt.option_letter} className={`qst-option-row ${isCorrect ? "qst-option-row--correct" : "qst-option-row--neutral"}`}>
                                                    <div className={`qst-option-letter-badge ${isCorrect ? "qst-option-letter-badge--correct" : "qst-option-letter-badge--neutral"}`}>
                                                        {opt.option_letter}
                                                    </div>
                                                    <div className="qst-option-content">
                                                        <span className="qst-option-text-label">{opt.option_text}</span>
                                                        {opt.option_image_path && <MediaImage path={opt.option_image_path} alt={`Opción ${opt.option_letter}`} maxHeight={120} />}
                                                        {isCorrect && <span className="qst-correct-pill-label">Clave Admin</span>}
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>

                                    {/* Resumen rápido R1/R2/R3 y advertencias */}
                                    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", flexWrap: "wrap", gap: "8px", fontSize: "0.78rem", color: "#94a3b8", borderTop: "1px solid var(--qst-border)", paddingTop: "10px" }}>
                                        <div style={{ display: "flex", gap: "10px", flexWrap: "wrap" }}>
                                            <span>R1: <strong style={{ color: "#f8fafc" }}>{res.r1_letter || "—"}</strong></span>
                                            <span>R2: <strong style={{ color: "#f8fafc" }}>{res.r2_letter || "—"}</strong></span>
                                            <span>R3: <strong style={{ color: "#f8fafc" }}>{res.r3_letter || "—"}</strong></span>
                                            <span>• Clave origen: <strong>{officialKey.has_official_key ? (officialKey.official_key_letter || "Sí") : "Sin clave oficial"}</strong></span>
                                        </div>

                                        <div style={{ display: "flex", gap: "8px", flexWrap: "wrap" }}>
                                            <button type="button" className="qst-btn-outline" onClick={() => onOpenDetail(q)}>
                                                <i className="fa-solid fa-magnifying-glass-chart"></i> Ver Detalle IQPE
                                            </button>

                                            <button type="button" className="qst-btn-action-edit" onClick={() => onEdit(q)}>
                                                <i className="fa-solid fa-pen-to-square"></i> Editar
                                            </button>

                                            {q.status === "draft" && (
                                                <button
                                                    type="button"
                                                    className="qst-btn-action-approve"
                                                    disabled={!eligibility.can_approve}
                                                    title={eligibility.can_approve ? "Aprobar pregunta (pasa a review)" : (eligibility.approve_blockers?.[0] || "Bloqueada")}
                                                    onClick={() => onRequestApprove(q, eligibility)}
                                                >
                                                    <i className="fa-solid fa-check"></i> Aprobar
                                                </button>
                                            )}

                                            {q.status === "review" && (
                                                <button
                                                    type="button"
                                                    className="qst-btn-save"
                                                    style={{ padding: "6px 12px", fontSize: "0.78rem", background: "linear-gradient(135deg, #2563eb 0%, #3b82f6 100%)" }}
                                                    disabled={!eligibility.can_publish}
                                                    title={eligibility.can_publish ? "Publicar pregunta para estudiantes" : (eligibility.publish_blockers?.[0] || "Faltan requisitos de publicación")}
                                                    onClick={() => onRequestPublish(q, eligibility)}
                                                >
                                                    <i className="fa-solid fa-globe"></i> Publicar
                                                </button>
                                            )}

                                            {(q.status === "review" || q.status === "archived" || q.status === "published") && (
                                                <button type="button" className="qst-btn-outline" onClick={() => onRequestReturnToReview(q)}>
                                                    <i className="fa-solid fa-rotate-left"></i> Devolver
                                                </button>
                                            )}

                                            {q.status !== "archived" && q.status !== "published" && (
                                                <button type="button" className="qst-btn-action-reject" onClick={() => onRequestReject(q)}>
                                                    <i className="fa-solid fa-ban"></i> Rechazar
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                </article>
                            );
                        })}
                    </div>
                )}

                {/* Paginación */}
                {meta.total > 0 && (
                    <div className="qst-table-footer" style={{ marginTop: "14px" }}>
                        <span>Mostrando {meta.from ?? 1}–{meta.to ?? items.length} de {meta.total} preguntas</span>
                        <div style={{ display: "flex", gap: "8px", alignItems: "center" }}>
                            <button
                                type="button"
                                className="qst-btn-outline"
                                disabled={meta.current_page <= 1 || isLoading}
                                onClick={() => fetchQueue(meta.current_page - 1)}
                            >
                                Anterior
                            </button>
                            <span style={{ fontSize: "0.8rem", color: "#94a3b8" }}>
                                Página {meta.current_page} de {meta.last_page}
                            </span>
                            <button
                                type="button"
                                className="qst-btn-outline"
                                disabled={meta.current_page >= meta.last_page || isLoading}
                                onClick={() => fetchQueue(meta.current_page + 1)}
                            >
                                Siguiente
                            </button>
                        </div>
                    </div>
                )}
            </section>
        </div>
    );
};

export default IqpeReviewInbox;

