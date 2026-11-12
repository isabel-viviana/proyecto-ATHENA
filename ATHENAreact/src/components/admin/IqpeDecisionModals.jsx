import { useState, useEffect } from "react";

export const IqpeReasonActionModal = ({
    isOpen,
    mode = "reject", // 'reject' | 'return_to_review'
    question,
    isLoading = false,
    onClose,
    onConfirm,
}) => {
    const [reason, setReason] = useState("");
    const [notes, setNotes] = useState("");
    const [localError, setLocalError] = useState("");

    useEffect(() => {
        if (isOpen) {
            setReason("");
            setNotes("");
            setLocalError("");
        }
    }, [isOpen, question?.id, mode]);

    if (!isOpen || !question) return null;

    const isReject = mode === "reject";
    const title = isReject ? "Rechazar y Archivar Reactivo" : "Devolver Reactivo a Revisión";
    const accentColor = isReject ? "#ef4444" : "#f59e0b";

    const handleSubmit = (e) => {
        e.preventDefault();
        const trimmed = reason.trim();
        if (trimmed.length < 5) {
            setLocalError("El motivo es obligatorio y debe contener al menos 5 caracteres.");
            return;
        }
        setLocalError("");
        onConfirm({ reason: trimmed, notes: notes.trim() || null });
    };

    return (
        <div className="qst-modal-overlay" style={{ zIndex: 1150 }} onClick={() => !isLoading && onClose()}>
            <div className="qst-modal-card" style={{ maxWidth: "520px" }} onClick={(e) => e.stopPropagation()}>
                <div className="qst-modal-header">
                    <h2 className="qst-modal-title" style={{ color: accentColor }}>
                        <i className={`fa-solid ${isReject ? "fa-ban" : "fa-rotate-left"}`}></i>
                        <span>{title}</span>
                    </h2>
                    <button
                        type="button"
                        className="qst-modal-close-btn"
                        onClick={() => !isLoading && onClose()}
                        disabled={isLoading}
                        aria-label="Cerrar"
                    >
                        <i className="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="qst-modal-body" style={{ display: "flex", flexDirection: "column", gap: "12px" }}>
                        <div style={{ background: "rgba(10, 17, 40, 0.7)", padding: "12px 14px", borderRadius: "8px", border: "1px solid var(--qst-border)" }}>
                            <div style={{ fontWeight: 700, color: "#ffffff", marginBottom: "4px" }}>
                                Pregunta #{question.order_in_group || question.id?.slice(0, 8)} • Estado actual: {question.status}
                            </div>
                            <div style={{ fontSize: "0.82rem", color: "#94a3b8", display: "-webkit-box", WebkitLineClamp: 2, WebkitBoxOrient: "vertical", overflow: "hidden" }}>
                                {question.statement}
                            </div>
                        </div>

                        <div style={{ fontSize: "0.82rem", color: "#cbd5e1", background: "rgba(56, 189, 248, 0.08)", border: "1px solid rgba(56, 189, 248, 0.25)", padding: "10px 12px", borderRadius: "8px" }}>
                            <i className="fa-solid fa-shield-halved" style={{ color: "#38bdf8", marginRight: "6px" }}></i>
                            {isReject
                                ? "La pregunta no será eliminada; pasará a estado archivado conservando intactas todas las resoluciones R1/R2/R3, auditorías, estímulos compartidos e historial."
                                : "La pregunta volverá a estado borrador para corrección pedagógica sin perder sus resoluciones IA, auditorías ni decisiones administrativas previas."}
                        </div>

                        <div className="qst-form-group" style={{ marginBottom: 0 }}>
                            <label className="qst-form-label" htmlFor="iqpe-action-reason">
                                {isReject ? "Motivo obligatorio del rechazo *" : "Motivo obligatorio para corrección *"}
                            </label>
                            <textarea
                                id="iqpe-action-reason"
                                className="qst-form-textarea"
                                rows={3}
                                placeholder={isReject
                                    ? "Ej: Pregunta duplicada contra banco publicado o enunciado irreproducible en el cuadernillo..."
                                    : "Ej: Requiere ajustar redacción de la opción C o confirmar clave oficial discrepante..."}
                                value={reason}
                                onChange={(e) => setReason(e.target.value)}
                                disabled={isLoading}
                                required
                            />
                        </div>

                        <div className="qst-form-group" style={{ marginBottom: 0 }}>
                            <label className="qst-form-label" htmlFor="iqpe-action-notes">
                                Observaciones administrativas adicionales (Opcional)
                            </label>
                            <textarea
                                id="iqpe-action-notes"
                                className="qst-form-textarea"
                                rows={2}
                                placeholder="Notas internas de trazabilidad..."
                                value={notes}
                                onChange={(e) => setNotes(e.target.value)}
                                disabled={isLoading}
                            />
                        </div>

                        {localError && (
                            <div style={{ color: "#fca5a5", fontSize: "0.82rem", background: "rgba(239, 68, 68, 0.12)", border: "1px solid rgba(239, 68, 68, 0.35)", padding: "8px 10px", borderRadius: "6px" }}>
                                <i className="fa-solid fa-circle-exclamation" style={{ marginRight: "6px" }}></i>
                                {localError}
                            </div>
                        )}
                    </div>

                    <div className="qst-modal-footer">
                        <button type="button" className="qst-btn-cancel" onClick={onClose} disabled={isLoading}>
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            className="qst-btn-save"
                            style={{
                                background: isReject
                                    ? "linear-gradient(135deg, #ef4444 0%, #dc2626 100%)"
                                    : "linear-gradient(135deg, #f59e0b 0%, #d97706 100%)",
                            }}
                            disabled={isLoading}
                        >
                            {isLoading ? (
                                <>
                                    <span className="qst-spinner" style={{ width: "14px", height: "14px" }}></span>
                                    <span>Guardando...</span>
                                </>
                            ) : (
                                <>
                                    <i className={`fa-solid ${isReject ? "fa-ban" : "fa-rotate-left"}`}></i>
                                    <span>{isReject ? "Confirmar Rechazo" : "Devolver a Revisión"}</span>
                                </>
                            )}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
};

export const IqpeApproveConfirmModal = ({
    isOpen,
    question,
    eligibility = null,
    isLoading = false,
    onClose,
    onConfirm,
}) => {
    const [notes, setNotes] = useState("");
    const [acknowledgeWarnings, setAcknowledgeWarnings] = useState(true);
    const [localError, setLocalError] = useState("");

    useEffect(() => {
        if (isOpen) {
            setNotes("");
            setAcknowledgeWarnings(true);
            setLocalError("");
        }
    }, [isOpen, question?.id]);

    if (!isOpen || !question) return null;

    const blockers = eligibility?.approve_blockers || [];
    const warnings = eligibility?.approve_warnings || [];
    const needsDiscrepancyNote = Boolean(eligibility?.has_discrepancy);

    const handleSubmit = (e) => {
        e.preventDefault();
        if (blockers.length > 0) return;
        if (needsDiscrepancyNote && !acknowledgeWarnings && !notes.trim()) {
            setLocalError("Debe confirmar la revisión humana de discrepancias o escribir una observación.");
            return;
        }
        setLocalError("");
        onConfirm({
            notes: notes.trim() || null,
            acknowledge_warnings: acknowledgeWarnings,
        });
    };

    return (
        <div className="qst-modal-overlay" style={{ zIndex: 1150 }} onClick={() => !isLoading && onClose()}>
            <div className="qst-modal-card" style={{ maxWidth: "560px" }} onClick={(e) => e.stopPropagation()}>
                <div className="qst-modal-header">
                    <h2 className="qst-modal-title" style={{ color: "#4ade80" }}>
                        <i className="fa-solid fa-circle-check"></i>
                        <span>Confirmar Aprobación Administrativa</span>
                    </h2>
                    <button type="button" className="qst-modal-close-btn" onClick={() => !isLoading && onClose()} disabled={isLoading}>
                        <i className="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="qst-modal-body" style={{ display: "flex", flexDirection: "column", gap: "12px" }}>
                        <div style={{ background: "rgba(10, 17, 40, 0.7)", padding: "12px 14px", borderRadius: "8px", border: "1px solid var(--qst-border)" }}>
                            <div style={{ fontWeight: 700, color: "#ffffff", marginBottom: "4px" }}>
                                Pregunta #{question.order_in_group || question.id?.slice(0, 8)}
                            </div>
                            <div style={{ fontSize: "0.82rem", color: "#94a3b8" }}>{question.statement}</div>
                        </div>

                        <div style={{ fontSize: "0.82rem", color: "#cbd5e1", background: "rgba(34, 197, 94, 0.08)", border: "1px solid rgba(34, 197, 94, 0.25)", padding: "10px 12px", borderRadius: "8px" }}>
                            <i className="fa-solid fa-circle-info" style={{ color: "#4ade80", marginRight: "6px" }}></i>
                            Al aprobar, la pregunta pasará al estado <strong>review</strong> (aprobada para control final). <strong>No se publicará automáticamente</strong> a estudiantes hasta ejecutar la acción independiente de publicación.
                        </div>

                        {blockers.length > 0 && (
                            <div style={{ background: "rgba(239, 68, 68, 0.12)", border: "1px solid rgba(239, 68, 68, 0.4)", borderRadius: "8px", padding: "12px", color: "#fecaca", fontSize: "0.82rem" }}>
                                <strong><i className="fa-solid fa-octagon-exclamation"></i> Bloqueos críticos que impiden aprobar:</strong>
                                <ul style={{ margin: "6px 0 0 18px", padding: 0 }}>
                                    {blockers.map((b, i) => <li key={i}>{b}</li>)}
                                </ul>
                            </div>
                        )}

                        {warnings.length > 0 && (
                            <div style={{ background: "rgba(245, 158, 11, 0.12)", border: "1px solid rgba(245, 158, 11, 0.4)", borderRadius: "8px", padding: "12px", color: "#fde68a", fontSize: "0.82rem" }}>
                                <strong><i className="fa-solid fa-triangle-exclamation"></i> Advertencias para consideración administrativa:</strong>
                                <ul style={{ margin: "6px 0 0 18px", padding: 0 }}>
                                    {warnings.map((w, i) => <li key={i}>{w}</li>)}
                                </ul>
                                <label style={{ display: "flex", alignItems: "center", gap: "8px", marginTop: "10px", cursor: "pointer", color: "#f8fafc", fontWeight: 600 }}>
                                    <input
                                        type="checkbox"
                                        checked={acknowledgeWarnings}
                                        onChange={(e) => setAcknowledgeWarnings(e.target.checked)}
                                        style={{ accentColor: "#f59e0b" }}
                                    />
                                    <span>Confirmo que revisé estas advertencias bajo criterio pedagógico</span>
                                </label>
                            </div>
                        )}

                        <div className="qst-form-group" style={{ marginBottom: 0 }}>
                            <label className="qst-form-label">Observaciones administrativas (Opcional)</label>
                            <textarea
                                className="qst-form-textarea"
                                rows={2}
                                placeholder="Ej: Revisado contra cuadernillo oficial, criterio validado..."
                                value={notes}
                                onChange={(e) => setNotes(e.target.value)}
                                disabled={isLoading}
                            />
                        </div>

                        {localError && (
                            <div style={{ color: "#fca5a5", fontSize: "0.82rem" }}>{localError}</div>
                        )}
                    </div>

                    <div className="qst-modal-footer">
                        <button type="button" className="qst-btn-cancel" onClick={onClose} disabled={isLoading}>
                            Cancelar
                        </button>
                        <button type="submit" className="qst-btn-save" disabled={isLoading || blockers.length > 0}>
                            {isLoading ? "Aprobando..." : "Confirmar Aprobación"}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
};

export const IqpePublishConfirmModal = ({
    isOpen,
    question,
    eligibility = null,
    isLoading = false,
    onClose,
    onConfirm,
}) => {
    const [notes, setNotes] = useState("");
    const [confirmDuplicate, setConfirmDuplicate] = useState(false);
    const [localError, setLocalError] = useState("");

    useEffect(() => {
        if (isOpen) {
            setNotes("");
            setConfirmDuplicate(false);
            setLocalError("");
        }
    }, [isOpen, question?.id]);

    if (!isOpen || !question) return null;

    const blockers = eligibility?.publish_blockers || [];
    const warnings = eligibility?.publish_warnings || [];
    const hasDuplicateRisk = Boolean(eligibility?.has_duplicate_risk);
    const canPublish = Boolean(eligibility?.can_publish) && blockers.length === 0;

    const handleSubmit = (e) => {
        e.preventDefault();
        if (!canPublish) return;
        if (hasDuplicateRisk && !confirmDuplicate && !notes.trim()) {
            setLocalError("La pregunta tiene una alerta de posible duplicado. Confirme explícitamente o registre una observación para publicar.");
            return;
        }
        setLocalError("");
        onConfirm({
            notes: notes.trim() || null,
            confirm_duplicate_publish: confirmDuplicate,
        });
    };

    const optionsCount = question.options?.length || 0;
    const correctOption = question.options?.find((o) => o.is_correct === true);

    return (
        <div className="qst-modal-overlay" style={{ zIndex: 1150 }} onClick={() => !isLoading && onClose()}>
            <div className="qst-modal-card" style={{ maxWidth: "560px" }} onClick={(e) => e.stopPropagation()}>
                <div className="qst-modal-header">
                    <h2 className="qst-modal-title" style={{ color: "#38bdf8" }}>
                        <i className="fa-solid fa-globe"></i>
                        <span>Confirmar Publicación al Banco Estudiantil</span>
                    </h2>
                    <button type="button" className="qst-modal-close-btn" onClick={() => !isLoading && onClose()} disabled={isLoading}>
                        <i className="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="qst-modal-body" style={{ display: "flex", flexDirection: "column", gap: "12px" }}>
                        <div style={{ background: "rgba(10, 17, 40, 0.7)", padding: "12px 14px", borderRadius: "8px", border: "1px solid var(--qst-border)" }}>
                            <div style={{ fontWeight: 700, color: "#ffffff", marginBottom: "6px" }}>
                                Pregunta #{question.order_in_group || question.id?.slice(0, 8)} • Estado actual: {question.status}
                            </div>
                            <div style={{ fontSize: "0.82rem", color: "#94a3b8", marginBottom: "8px" }}>{question.statement}</div>
                            <div style={{ display: "flex", gap: "8px", flexWrap: "wrap", fontSize: "0.75rem" }}>
                                <span style={{ padding: "3px 8px", borderRadius: "6px", background: optionsCount === 4 ? "rgba(34,197,94,0.15)" : "rgba(239,68,68,0.15)", color: optionsCount === 4 ? "#4ade80" : "#fca5a5" }}>
                                    Opciones: {optionsCount}/4
                                </span>
                                <span style={{ padding: "3px 8px", borderRadius: "6px", background: question.key_status === "verified" && correctOption ? "rgba(34,197,94,0.15)" : "rgba(239,68,68,0.15)", color: question.key_status === "verified" && correctOption ? "#4ade80" : "#fca5a5" }}>
                                    Clave verificada: {correctOption ? correctOption.option_letter : "Sin asignar"}
                                </span>
                                <span style={{ padding: "3px 8px", borderRadius: "6px", background: question.status === "review" ? "rgba(56,189,248,0.15)" : "rgba(245,158,11,0.15)", color: question.status === "review" ? "#38bdf8" : "#fbbf24" }}>
                                    Aprobación previa: {question.status === "review" ? "Sí (review)" : question.status}
                                </span>
                            </div>
                        </div>

                        {blockers.length > 0 && (
                            <div style={{ background: "rgba(239, 68, 68, 0.12)", border: "1px solid rgba(239, 68, 68, 0.4)", borderRadius: "8px", padding: "12px", color: "#fecaca", fontSize: "0.82rem" }}>
                                <strong><i className="fa-solid fa-ban"></i> Requisitos incumplidos para publicar:</strong>
                                <ul style={{ margin: "6px 0 0 18px", padding: 0 }}>
                                    {blockers.map((b, i) => <li key={i}>{b}</li>)}
                                </ul>
                            </div>
                        )}

                        {warnings.length > 0 && (
                            <div style={{ background: "rgba(245, 158, 11, 0.12)", border: "1px solid rgba(245, 158, 11, 0.4)", borderRadius: "8px", padding: "12px", color: "#fde68a", fontSize: "0.82rem" }}>
                                <strong><i className="fa-solid fa-triangle-exclamation"></i> Advertencias previas a publicación:</strong>
                                <ul style={{ margin: "6px 0 0 18px", padding: 0 }}>
                                    {warnings.map((w, i) => <li key={i}>{w}</li>)}
                                </ul>
                                {hasDuplicateRisk && (
                                    <label style={{ display: "flex", alignItems: "center", gap: "8px", marginTop: "10px", cursor: "pointer", color: "#f8fafc", fontWeight: 600 }}>
                                        <input
                                            type="checkbox"
                                            checked={confirmDuplicate}
                                            onChange={(e) => setConfirmDuplicate(e.target.checked)}
                                            style={{ accentColor: "#f59e0b" }}
                                        />
                                        <span>Confirmo publicar pese a la advertencia de posible duplicado</span>
                                    </label>
                                )}
                            </div>
                        )}

                        <div style={{ fontSize: "0.8rem", color: "#94a3b8" }}>
                            <i className="fa-solid fa-users" style={{ color: "#38bdf8", marginRight: "6px" }}></i>
                            Una vez publicada (<strong>published</strong>), los estudiantes podrán verla y resolverla en prácticas y simulacros sin exposición de respuestas correctas antes de responder.
                        </div>

                        <div className="qst-form-group" style={{ marginBottom: 0 }}>
                            <label className="qst-form-label">Nota de publicación (Opcional)</label>
                            <textarea
                                className="qst-form-textarea"
                                rows={2}
                                placeholder="Ej: Publicación validada para banco Saber 11..."
                                value={notes}
                                onChange={(e) => setNotes(e.target.value)}
                                disabled={isLoading}
                            />
                        </div>

                        {localError && <div style={{ color: "#fca5a5", fontSize: "0.82rem" }}>{localError}</div>}
                    </div>

                    <div className="qst-modal-footer">
                        <button type="button" className="qst-btn-cancel" onClick={onClose} disabled={isLoading}>
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            className="qst-btn-save"
                            style={{ background: "linear-gradient(135deg, #2563eb 0%, #3b82f6 100%)" }}
                            disabled={isLoading || !canPublish}
                        >
                            {isLoading ? "Publicando..." : "Publicar Ahora"}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
};

