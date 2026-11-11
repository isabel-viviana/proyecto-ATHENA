import { useState, useEffect, useCallback } from "react";
import { getQuestionIqpeDetail } from "../../api/documents";
import { MediaImage, StimulusMedia, QuestionMedia } from "./QuestionMedia";

const SEVERITY_STYLE = {
    critical: { bg: "rgba(239, 68, 68, 0.14)", border: "rgba(239, 68, 68, 0.45)", color: "#fca5a5", label: "CRÍTICA" },
    high: { bg: "rgba(249, 115, 22, 0.14)", border: "rgba(249, 115, 22, 0.45)", color: "#fdba74", label: "ALTA" },
    medium: { bg: "rgba(245, 158, 11, 0.14)", border: "rgba(245, 158, 11, 0.45)", color: "#fde68a", label: "MEDIA" },
    low: { bg: "rgba(56, 189, 248, 0.14)", border: "rgba(56, 189, 248, 0.4)", color: "#7dd3fc", label: "BAJA" },
};

const DUP_LABEL = {
    exact_duplicate: { text: "Duplicado Exacto", color: "#f87171", bg: "rgba(239,68,68,0.16)" },
    probable_duplicate: { text: "Duplicado Probable", color: "#fbbf24", bg: "rgba(245,158,11,0.16)" },
    related_question: { text: "Pregunta Relacionada", color: "#38bdf8", bg: "rgba(56,189,248,0.14)" },
    independent: { text: "Independiente", color: "#4ade80", bg: "rgba(34,197,94,0.14)" },
};

const ResolutionCard = ({ title, resolution, officialLetter }) => {
    if (!resolution) {
        return (
            <div className="qst-iqpe-res-card qst-iqpe-res-card--empty">
                <div className="qst-iqpe-res-head">
                    <strong>{title}</strong>
                    <span className="qst-iqpe-pill qst-iqpe-pill--muted">No ejecutada</span>
                </div>
                <p style={{ margin: 0, fontSize: "0.78rem", color: "#64748b" }}>
                    No se requirió o no existe registro de esta ronda para la pregunta.
                </p>
            </div>
        );
    }

    const matchesKey = officialLetter && resolution.solved_option_letter
        ? resolution.solved_option_letter === officialLetter
        : null;

    return (
        <div className="qst-iqpe-res-card">
            <div className="qst-iqpe-res-head">
                <div style={{ display: "flex", alignItems: "center", gap: "8px", flexWrap: "wrap" }}>
                    <strong style={{ color: "#f8fafc" }}>{title}</strong>
                    <span className="qst-iqpe-pill qst-iqpe-pill--blue">
                        Respuesta: {resolution.solved_option_letter || "Incierta"}
                    </span>
                    {matchesKey === true && (
                        <span className="qst-iqpe-pill qst-iqpe-pill--green">Coincide con clave</span>
                    )}
                    {matchesKey === false && (
                        <span className="qst-iqpe-pill qst-iqpe-pill--red">Difiere de clave ({officialLetter})</span>
                    )}
                </div>
                <span className={`qst-iqpe-pill ${resolution.status === "success" ? "qst-iqpe-pill--green" : "qst-iqpe-pill--amber"}`}>
                    {resolution.status}
                </span>
            </div>

            <div className="qst-iqpe-res-metrics">
                <span><i className="fa-solid fa-microchip"></i> Modelo: <strong>{resolution.ai_model || "—"}</strong></span>
                <span><i className="fa-solid fa-gauge-high"></i> Confianza: <strong>{resolution.confidence_score != null ? `${Math.round(resolution.confidence_score * 100)}%` : "—"}</strong></span>
                <span><i className="fa-solid fa-stopwatch"></i> Latencia: <strong>{resolution.latency_ms != null ? `${resolution.latency_ms} ms` : "—"}</strong></span>
                <span><i className="fa-solid fa-coins"></i> Tokens: <strong>{resolution.total_tokens != null ? `${resolution.total_tokens} (${resolution.prompt_tokens ?? 0} in / ${resolution.completion_tokens ?? 0} out)` : "—"}</strong></span>
            </div>

            <div style={{ fontSize: "0.82rem", color: "#e2e8f0", lineHeight: 1.5, background: "rgba(10, 17, 40, 0.6)", padding: "10px 12px", borderRadius: "8px" }}>
                <strong style={{ color: "#93c5fd", display: "block", marginBottom: "4px" }}>Explicación R{resolution.round_number}:</strong>
                {resolution.solution_explanation || "Sin explicación registrada."}
            </div>

            {resolution.visual_interpretation && (
                <div style={{ fontSize: "0.78rem", color: "#cbd5e1", background: "rgba(168, 85, 247, 0.08)", border: "1px solid rgba(168, 85, 247, 0.25)", padding: "8px 10px", borderRadius: "6px" }}>
                    <strong style={{ color: "#d8b4fe" }}>Interpretación visual:</strong> {resolution.visual_interpretation}
                </div>
            )}
        </div>
    );
};

const IqpeQuestionDetailModal = ({
    isOpen,
    questionId,
    refreshNonce = 0,
    onClose,
    onEdit,
    onRequestApprove,
    onRequestReject,
    onRequestReturnToReview,
    onRequestPublish,
}) => {
    const [detail, setDetail] = useState(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState(null);

    const loadDetail = useCallback(async () => {
        if (!questionId) return;
        setIsLoading(true);
        setError(null);
        try {
            const res = await getQuestionIqpeDetail(questionId);
            if (res?.success) {
                setDetail(res.data);
            } else {
                setError(res?.message || "No fue posible cargar el detalle de revisión.");
            }
        } catch (err) {
            setError(err?.response?.data?.message || err.message || "Error de conexión al consultar detalle IQPE.");
        } finally {
            setIsLoading(false);
        }
    }, [questionId]);

    useEffect(() => {
        if (isOpen && questionId) {
            loadDetail();
        }
    }, [isOpen, questionId, refreshNonce, loadDetail]);

    if (!isOpen || !questionId) return null;

    const q = detail?.question;
    const resolutions = detail?.resolutions || {};
    const officialKey = detail?.official_key || {};
    const verification = detail?.verification;
    const audit = detail?.quality_audit;
    const duplicates = detail?.duplicates || {};
    const decisions = detail?.admin_decisions || [];
    const eligibility = detail?.eligibility || {};
    const compareKey = officialKey.official_key_letter || officialKey.current_marked_letter || null;
    const dupMeta = DUP_LABEL[duplicates.classification] || DUP_LABEL.independent;

    return (
        <div className="qst-modal-overlay" onClick={onClose}>
            <div
                className="qst-modal-card qst-iqpe-detail-modal"
                style={{ maxWidth: "1060px", width: "96vw" }}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="qst-modal-header">
                    <div>
                        <h2 className="qst-modal-title">
                            <i className="fa-solid fa-magnifying-glass-chart" style={{ color: "#38bdf8" }}></i>
                            <span>Detalle de Revisión IQPE — Pregunta #{q?.order_in_group || questionId.slice(0, 8)}</span>
                        </h2>
                        <div style={{ display: "flex", gap: "8px", flexWrap: "wrap", marginTop: "4px", fontSize: "0.76rem", color: "#94a3b8" }}>
                            <span>ID: <code style={{ color: "#cbd5e1" }}>{questionId}</code></span>
                            {q?.subject?.name && <span>• Área: <strong style={{ color: "#38bdf8" }}>{q.subject.name}</strong></span>}
                            {q?.topic?.name && <span>• Tema: <strong>{q.topic.name}</strong></span>}
                            {q?.status && <span>• Estado: <strong style={{ color: "#f8fafc" }}>{q.status}</strong></span>}
                        </div>
                    </div>
                    <button type="button" className="qst-modal-close-btn" onClick={onClose} aria-label="Cerrar detalle">
                        <i className="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div className="qst-modal-body" style={{ maxHeight: "76vh", overflowY: "auto", display: "flex", flexDirection: "column", gap: "18px" }}>
                    {isLoading ? (
                        <div style={{ textAlign: "center", padding: "48px 20px" }}>
                            <span className="qst-spinner" style={{ width: "36px", height: "36px", borderWidth: "3px" }}></span>
                            <p style={{ color: "#94a3b8", marginTop: "12px" }}>Cargando resoluciones R1/R2/R3, clave oficial, auditoría y duplicados...</p>
                        </div>
                    ) : error ? (
                        <div className="qst-alert qst-alert--error">
                            <span>{error}</span>
                        </div>
                    ) : detail && q ? (
                        <>
                            {/* 1. BLOQUEOS Y ADVERTENCIAS DE ELEGIBILIDAD */}
                            {(eligibility.approve_blockers?.length > 0 || eligibility.approve_warnings?.length > 0 || eligibility.publish_blockers?.length > 0) && (
                                <div style={{ display: "flex", flexDirection: "column", gap: "8px" }}>
                                    {eligibility.approve_blockers?.length > 0 && (
                                        <div style={{ background: "rgba(239, 68, 68, 0.12)", border: "1px solid rgba(239, 68, 68, 0.45)", borderRadius: "10px", padding: "12px 14px", color: "#fecaca", fontSize: "0.82rem" }}>
                                            <strong><i className="fa-solid fa-octagon-exclamation"></i> Bloqueos Críticos (Aprobación impedida hasta corregir):</strong>
                                            <ul style={{ margin: "6px 0 0 18px", padding: 0 }}>
                                                {eligibility.approve_blockers.map((b, idx) => <li key={idx}>{b}</li>)}
                                            </ul>
                                        </div>
                                    )}
                                    {eligibility.approve_warnings?.length > 0 && (
                                        <div style={{ background: "rgba(245, 158, 11, 0.12)", border: "1px solid rgba(245, 158, 11, 0.4)", borderRadius: "10px", padding: "12px 14px", color: "#fde68a", fontSize: "0.82rem" }}>
                                            <strong><i className="fa-solid fa-triangle-exclamation"></i> Advertencias de Revisión Administrativa:</strong>
                                            <ul style={{ margin: "6px 0 0 18px", padding: 0 }}>
                                                {eligibility.approve_warnings.map((w, idx) => <li key={idx}>{w}</li>)}
                                            </ul>
                                        </div>
                                    )}
                                </div>
                            )}

                            {/* 2. CONTEXTO / ESTÍMULO, ENUNCIADO Y OPCIONES */}
                            <section className="qst-iqpe-section">
                                <h3 className="qst-iqpe-section-title">
                                    <i className="fa-solid fa-file-lines"></i>
                                    <span>Enunciado, Opciones, Estímulo y Recursos Visuales</span>
                                </h3>

                                {q.stimulus && (
                                    <div style={{ background: "rgba(15, 23, 42, 0.7)", border: "1px solid rgba(148,163,184,0.2)", borderRadius: "8px", padding: "12px", marginBottom: "10px" }}>
                                        <div style={{ fontSize: "0.78rem", color: "#38bdf8", fontWeight: 700, marginBottom: "4px" }}>
                                            Estímulo Compartido: {q.stimulus.title || q.stimulus.id}
                                        </div>
                                        {q.stimulus.instructions && <div style={{ fontSize: "0.78rem", color: "#94a3b8", marginBottom: "6px" }}>{q.stimulus.instructions}</div>}
                                        {q.stimulus.content_text && <div style={{ fontSize: "0.84rem", color: "#e2e8f0", lineHeight: 1.5, whiteSpace: "pre-wrap" }}>{q.stimulus.content_text}</div>}
                                        <StimulusMedia stimulus={q.stimulus} />
                                    </div>
                                )}

                                <div style={{ fontSize: "0.95rem", color: "#ffffff", lineHeight: 1.55, marginBottom: "10px", whiteSpace: "pre-wrap" }}>
                                    {q.statement}
                                </div>
                                <QuestionMedia question={q} />

                                <div className="qst-options-list-grid" style={{ marginTop: "10px" }}>
                                    {(q.options || []).map((opt) => {
                                        const isCorrect = opt.is_correct === true;
                                        return (
                                            <div key={opt.id || opt.option_letter} className={`qst-option-row ${isCorrect ? "qst-option-row--correct" : "qst-option-row--neutral"}`}>
                                                <div className={`qst-option-letter-badge ${isCorrect ? "qst-option-letter-badge--correct" : "qst-option-letter-badge--neutral"}`}>
                                                    {opt.option_letter}
                                                </div>
                                                <div className="qst-option-content">
                                                    <span className="qst-option-text-label">{opt.option_text}</span>
                                                    {opt.option_image_path && <MediaImage path={opt.option_image_path} alt={`Opción ${opt.option_letter}`} maxHeight={160} />}
                                                    {isCorrect && (
                                                        <span className="qst-correct-pill-label">
                                                            <i className="fa-solid fa-key"></i> Marcada Correcta (Solo Admin)
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </section>

                            {/* 3. CLAVE OFICIAL Y COMPARACIÓN DE VERIFICACIÓN */}
                            <section className="qst-iqpe-section">
                                <h3 className="qst-iqpe-section-title">
                                    <i className="fa-solid fa-key" style={{ color: "#34d399" }}></i>
                                    <span>Clave Oficial (Exclusivo Administrativo) & Verificación IQPE</span>
                                </h3>

                                <div className="qst-iqpe-two-col">
                                    <div className="qst-iqpe-info-box">
                                        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "8px" }}>
                                            <strong style={{ color: "#f8fafc" }}>Clave Oficial</strong>
                                            <span className={`qst-iqpe-pill ${officialKey.has_official_key_in_source ? "qst-iqpe-pill--green" : "qst-iqpe-pill--amber"}`}>
                                                {officialKey.has_official_key_in_source ? "Existe clave en documento" : "Sin clave oficial en origen"}
                                            </span>
                                        </div>
                                        <div style={{ fontSize: "0.84rem", color: "#cbd5e1", display: "flex", flexDirection: "column", gap: "4px" }}>
                                            <span>Clave oficial documento: <strong style={{ color: "#4ade80" }}>{officialKey.official_key_letter || "No registrada"}</strong></span>
                                            <span>Clave calibrada actual: <strong style={{ color: "#38bdf8" }}>{officialKey.current_marked_letter || "Sin asignar"}</strong> ({officialKey.key_status})</span>
                                            {officialKey.manual_key_assigned && (
                                                <span style={{ color: "#fbbf24", fontWeight: 700 }}>
                                                    <i className="fa-solid fa-user-pen"></i> Clave asignada manualmente por administrador
                                                </span>
                                            )}
                                            <div style={{ display: "flex", gap: "8px", flexWrap: "wrap", marginTop: "4px", fontSize: "0.76rem" }}>
                                                {officialKey.matches_r1 !== null && (
                                                    <span className={`qst-iqpe-pill ${officialKey.matches_r1 ? "qst-iqpe-pill--green" : "qst-iqpe-pill--red"}`}>
                                                        R1 {officialKey.matches_r1 ? "coincide" : "difiere"}
                                                    </span>
                                                )}
                                                {officialKey.matches_r2 !== null && (
                                                    <span className={`qst-iqpe-pill ${officialKey.matches_r2 ? "qst-iqpe-pill--green" : "qst-iqpe-pill--red"}`}>
                                                        R2 {officialKey.matches_r2 ? "coincide" : "difiere"}
                                                    </span>
                                                )}
                                                {officialKey.matches_r3 !== null && (
                                                    <span className={`qst-iqpe-pill ${officialKey.matches_r3 ? "qst-iqpe-pill--green" : "qst-iqpe-pill--red"}`}>
                                                        R3 {officialKey.matches_r3 ? "coincide" : "difiere"}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                        {officialKey.warnings?.length > 0 && (
                                            <div style={{ marginTop: "8px", fontSize: "0.78rem", color: "#fde68a", background: "rgba(245, 158, 11, 0.1)", padding: "8px", borderRadius: "6px" }}>
                                                {officialKey.warnings.map((w, i) => <div key={i}>• {w}</div>)}
                                            </div>
                                        )}
                                    </div>

                                    <div className="qst-iqpe-info-box">
                                        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "8px" }}>
                                            <strong style={{ color: "#f8fafc" }}>Resultado de Verificación</strong>
                                            {verification ? (
                                                <span className={`qst-iqpe-pill ${verification.requires_admin_review ? "qst-iqpe-pill--amber" : "qst-iqpe-pill--green"}`}>
                                                    {verification.verdict}
                                                </span>
                                            ) : (
                                                <span className="qst-iqpe-pill qst-iqpe-pill--muted">Sin verificación IQPE</span>
                                            )}
                                        </div>
                                        {verification ? (
                                            <div style={{ fontSize: "0.83rem", color: "#cbd5e1", display: "flex", flexDirection: "column", gap: "4px" }}>
                                                <span>Estado recomendado: <strong>{verification.recommended_status}</strong></span>
                                                <span>Requiere revisión humana: <strong>{verification.requires_admin_review ? "Sí" : "No"}</strong></span>
                                                <span>Aprobada por admin: <strong>{verification.is_approved_by_admin ? "Sí" : "No"}</strong></span>
                                                <span>Índice de confianza: <strong>{Math.round((verification.confidence_index || 0) * 100)}%</strong></span>
                                                {verification.discrepancy_details && (
                                                    <div style={{ marginTop: "6px", color: "#fde68a", background: "rgba(245,158,11,0.08)", padding: "8px", borderRadius: "6px", fontSize: "0.78rem" }}>
                                                        <strong>Detalle:</strong> {verification.discrepancy_details}
                                                    </div>
                                                )}
                                            </div>
                                        ) : (
                                            <p style={{ margin: 0, fontSize: "0.8rem", color: "#64748b" }}>Esta pregunta aún no posee registro de verificación IQPE.</p>
                                        )}
                                    </div>
                                </div>
                            </section>

                            {/* 4. RESOLUCIONES INDEPENDIENTES R1, R2 Y R3 */}
                            <section className="qst-iqpe-section">
                                <h3 className="qst-iqpe-section-title">
                                    <i className="fa-solid fa-robot" style={{ color: "#a855f7" }}></i>
                                    <span>Resoluciones Independientes de IA (R1, R2 y R3)</span>
                                </h3>
                                <div className="qst-iqpe-res-grid">
                                    <ResolutionCard title="R1 — Primera Resolución Independiente" resolution={resolutions.r1} officialLetter={compareKey} />
                                    <ResolutionCard title="R2 — Segunda Resolución Independiente" resolution={resolutions.r2} officialLetter={compareKey} />
                                    <ResolutionCard title="R3 — Tercera Resolución (Sin Clave Oficial)" resolution={resolutions.r3} officialLetter={compareKey} />
                                </div>
                            </section>

                            {/* 5. AUDITORÍA DE CALIDAD Y DETECCIÓN DE DUPLICADOS */}
                            <div className="qst-iqpe-two-col">
                                <section className="qst-iqpe-section">
                                    <h3 className="qst-iqpe-section-title">
                                        <i className="fa-solid fa-shield-halved" style={{ color: "#38bdf8" }}></i>
                                        <span>Auditoría de Calidad</span>
                                    </h3>
                                    {audit ? (
                                        <div style={{ display: "flex", flexDirection: "column", gap: "8px" }}>
                                            <div style={{ display: "flex", gap: "8px", flexWrap: "wrap", fontSize: "0.76rem" }}>
                                                <span className={`qst-iqpe-pill ${audit.passed_quality_check ? "qst-iqpe-pill--green" : "qst-iqpe-pill--red"}`}>
                                                    {audit.overall_classification}
                                                </span>
                                                <span className="qst-iqpe-pill qst-iqpe-pill--muted">
                                                    Enunciado: {Math.round((audit.statement_integrity_score || 0) * 100)}%
                                                </span>
                                                <span className="qst-iqpe-pill qst-iqpe-pill--muted">
                                                    Opciones: {Math.round((audit.options_integrity_score || 0) * 100)}%
                                                </span>
                                                <span className="qst-iqpe-pill qst-iqpe-pill--muted">
                                                    Media: {audit.media_integrity_status}
                                                </span>
                                            </div>
                                            {audit.findings?.length > 0 ? (
                                                audit.findings.map((f, idx) => {
                                                    const st = SEVERITY_STYLE[f.severity] || SEVERITY_STYLE.low;
                                                    return (
                                                        <div key={idx} style={{ background: st.bg, border: `1px solid ${st.border}`, borderRadius: "8px", padding: "10px", fontSize: "0.79rem", color: "#f8fafc" }}>
                                                            <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "4px" }}>
                                                                <strong style={{ color: st.color }}>{f.type}</strong>
                                                                <span style={{ fontWeight: 800, color: st.color, fontSize: "0.72rem" }}>{st.label}</span>
                                                            </div>
                                                            <div style={{ marginBottom: "4px" }}>{f.description}</div>
                                                            {f.recommendation && (
                                                                <div style={{ color: "#cbd5e1", fontSize: "0.75rem" }}>
                                                                    <strong>Recomendación:</strong> {f.recommendation}
                                                                </div>
                                                            )}
                                                            {f.evidence && (
                                                                <details style={{ marginTop: "4px", fontSize: "0.72rem", color: "#94a3b8" }}>
                                                                    <summary style={{ cursor: "pointer" }}>Ver evidencia verificable</summary>
                                                                    <pre style={{ margin: "4px 0 0 0", whiteSpace: "pre-wrap", fontFamily: "monospace" }}>
                                                                        {JSON.stringify(f.evidence, null, 2)}
                                                                    </pre>
                                                                </details>
                                                            )}
                                                        </div>
                                                    );
                                                })
                                            ) : (
                                                <div style={{ color: "#4ade80", fontSize: "0.82rem" }}>
                                                    <i className="fa-solid fa-circle-check"></i> Sin hallazgos ni anomalías de calidad.
                                                </div>
                                            )}
                                        </div>
                                    ) : (
                                        <p style={{ margin: 0, fontSize: "0.8rem", color: "#64748b" }}>No existe auditoría de calidad registrada todavía.</p>
                                    )}
                                </section>

                                <section className="qst-iqpe-section">
                                    <h3 className="qst-iqpe-section-title">
                                        <i className="fa-solid fa-clone" style={{ color: "#fbbf24" }}></i>
                                        <span>Comparativa de Duplicados (Informativo)</span>
                                    </h3>
                                    <div style={{ display: "flex", alignItems: "center", gap: "8px", marginBottom: "8px", flexWrap: "wrap" }}>
                                        <span className="qst-iqpe-pill" style={{ background: dupMeta.bg, color: dupMeta.color }}>
                                            {dupMeta.text}
                                        </span>
                                        {duplicates.involves_published_question && (
                                            <span className="qst-iqpe-pill qst-iqpe-pill--red">
                                                <i className="fa-solid fa-triangle-exclamation"></i> Coincide con pregunta PUBLICADA
                                            </span>
                                        )}
                                    </div>
                                    {duplicates.matches?.length > 0 ? (
                                        <div style={{ display: "flex", flexDirection: "column", gap: "8px" }}>
                                            {duplicates.matches.map((m, idx) => (
                                                <div key={idx} style={{ background: "rgba(10, 17, 40, 0.7)", border: "1px solid var(--qst-border)", borderRadius: "8px", padding: "10px", fontSize: "0.79rem" }}>
                                                    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "4px", gap: "6px", flexWrap: "wrap" }}>
                                                        <strong style={{ color: "#f8fafc" }}>
                                                            Candidata #{m.candidate_question?.order_in_group || m.candidate_question_id?.slice(0, 8)}
                                                        </strong>
                                                        <div style={{ display: "flex", gap: "6px" }}>
                                                            <span className="qst-iqpe-pill qst-iqpe-pill--amber">
                                                                Similitud: {Math.round((m.similarity_score || 0) * 100)}%
                                                            </span>
                                                            {m.candidate_question?.status === "published" && (
                                                                <span className="qst-iqpe-pill qst-iqpe-pill--red">YA PUBLICADA</span>
                                                            )}
                                                        </div>
                                                    </div>
                                                    <div style={{ color: "#cbd5e1", marginBottom: "4px" }}>
                                                        {m.candidate_question?.statement || "Pregunta del banco"}
                                                    </div>
                                                    <div style={{ fontSize: "0.72rem", color: "#94a3b8" }}>
                                                        Clasificación: <strong>{m.classification}</strong> • Estado: <strong>{m.candidate_question?.status || m.candidate_status}</strong>
                                                    </div>
                                                </div>
                                            ))}
                                            <div style={{ fontSize: "0.74rem", color: "#94a3b8" }}>
                                                <i className="fa-solid fa-shield-halved"></i> Vista estrictamente comparativa: no se eliminan ni fusionan preguntas desde aquí.
                                            </div>
                                        </div>
                                    ) : (
                                        <p style={{ margin: 0, fontSize: "0.82rem", color: "#4ade80" }}>
                                            No se detectaron similitudes relevantes ni duplicados contra el banco activo.
                                        </p>
                                    )}
                                </section>
                            </div>

                            {/* 6. TRAZABILIDAD / HISTORIAL DE DECISIONES */}
                            <section className="qst-iqpe-section">
                                <h3 className="qst-iqpe-section-title">
                                    <i className="fa-solid fa-clock-rotate-left" style={{ color: "#94a3b8" }}></i>
                                    <span>Historial Administrativo de Decisiones y Cambios</span>
                                </h3>
                                {decisions.length > 0 ? (
                                    <div style={{ display: "flex", flexDirection: "column", gap: "6px" }}>
                                        {decisions.map((d) => (
                                            <div key={d.id} style={{ background: "rgba(10, 17, 40, 0.55)", border: "1px solid var(--qst-border)", borderRadius: "6px", padding: "8px 12px", fontSize: "0.78rem", display: "flex", justifyContent: "space-between", gap: "10px", flexWrap: "wrap" }}>
                                                <div>
                                                    <strong style={{ color: "#38bdf8", textTransform: "uppercase" }}>{d.action}</strong>
                                                    {" "}por <strong>{d.admin_name}</strong>
                                                    {d.previous_status && d.new_status && (
                                                        <span style={{ color: "#94a3b8" }}> ({d.previous_status} → {d.new_status})</span>
                                                    )}
                                                    {d.manual_key_assigned && (
                                                        <span style={{ color: "#fbbf24", marginLeft: "6px" }}>[Clave asignada manualmente]</span>
                                                    )}
                                                    {(d.reason || d.notes) && (
                                                        <div style={{ color: "#cbd5e1", marginTop: "2px" }}>
                                                            {d.reason || d.notes}
                                                        </div>
                                                    )}
                                                </div>
                                                <span style={{ color: "#64748b", fontSize: "0.72rem" }}>
                                                    {d.created_at ? new Date(d.created_at).toLocaleString("es-CO") : "—"}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <p style={{ margin: 0, fontSize: "0.8rem", color: "#64748b" }}>Aún no hay decisiones administrativas registradas para esta pregunta.</p>
                                )}
                            </section>
                        </>
                    ) : null}
                </div>

                {/* BARRA DE ACCIONES EN PIE DE MODAL */}
                {detail && q && (
                    <div className="qst-modal-footer" style={{ justifyContent: "space-between", flexWrap: "wrap", gap: "10px" }}>
                        <div style={{ display: "flex", gap: "8px", flexWrap: "wrap" }}>
                            <button type="button" className="qst-btn-action-edit" onClick={() => onEdit && onEdit(q)}>
                                <i className="fa-solid fa-pen-to-square"></i>
                                <span>Editar / Corregir</span>
                            </button>

                            {(q.status === "review" || q.status === "archived" || q.status === "published") && (
                                <button
                                    type="button"
                                    className="qst-btn-outline"
                                    onClick={() => onRequestReturnToReview && onRequestReturnToReview(q)}
                                >
                                    <i className="fa-solid fa-rotate-left"></i>
                                    <span>Devolver a Revisión</span>
                                </button>
                            )}

                            {q.status !== "archived" && q.status !== "published" && (
                                <button type="button" className="qst-btn-action-reject" onClick={() => onRequestReject && onRequestReject(q)}>
                                    <i className="fa-solid fa-ban"></i>
                                    <span>Rechazar</span>
                                </button>
                            )}
                        </div>

                        <div style={{ display: "flex", gap: "8px", flexWrap: "wrap" }}>
                            {q.status === "draft" && (
                                <button
                                    type="button"
                                    className="qst-btn-save"
                                    disabled={!eligibility.can_approve}
                                    title={eligibility.can_approve ? "Aprobar pregunta (pasa a estado review)" : (eligibility.approve_blockers?.[0] || "Bloqueada")}
                                    onClick={() => onRequestApprove && onRequestApprove(q, eligibility)}
                                >
                                    <i className="fa-solid fa-circle-check"></i>
                                    <span>Aprobar Reactivo</span>
                                </button>
                            )}

                            {q.status === "review" && (
                                <button
                                    type="button"
                                    className="qst-btn-save"
                                    style={{ background: "linear-gradient(135deg, #2563eb 0%, #3b82f6 100%)" }}
                                    disabled={!eligibility.can_publish}
                                    title={eligibility.can_publish ? "Publicar al banco estudiantil" : (eligibility.publish_blockers?.[0] || "No cumple requisitos")}
                                    onClick={() => onRequestPublish && onRequestPublish(q, eligibility)}
                                >
                                    <i className="fa-solid fa-globe"></i>
                                    <span>Publicar</span>
                                </button>
                            )}

                            <button type="button" className="qst-btn-cancel" onClick={onClose}>
                                Cerrar
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

export default IqpeQuestionDetailModal;

