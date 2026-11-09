import { useState, useEffect, useMemo } from "react";
import {
    getMediaUrl,
    associateMediaAsset,
    disassociateMediaAsset,
} from "../../api/documents";

/**
 * Imagen servida por el backend con proporciones originales, ampliable (lightbox)
 * y con fallback si el recurso no carga (evita imágenes rotas).
 */
export const MediaImage = ({ path, alt, maxHeight = 360, meta = null, showZoomButton = false }) => {
    const [failed, setFailed] = useState(false);
    const [open, setOpen] = useState(false);
    const url = getMediaUrl(path);

    useEffect(() => {
        setFailed(false);
    }, [path]);

    useEffect(() => {
        if (!open) return undefined;
        const onKey = (e) => e.key === "Escape" && setOpen(false);
        window.addEventListener("keydown", onKey);
        return () => window.removeEventListener("keydown", onKey);
    }, [open]);

    if (!url || failed) {
        return (
            <div
                className="qst-media-missing"
                style={{
                    display: "flex",
                    alignItems: "center",
                    gap: 8,
                    padding: "10px 12px",
                    borderRadius: 8,
                    background: "rgba(15, 23, 42, 0.6)",
                    border: "1px dashed rgba(148, 163, 184, 0.35)",
                    color: "#94a3b8",
                    fontSize: "0.8rem",
                }}
            >
                <i className="fa-solid fa-image"></i>
                <span>No se pudo cargar el recurso visual.</span>
            </div>
        );
    }

    return (
        <>
            <div style={{ display: "flex", flexDirection: "column", gap: 6, alignItems: "flex-start" }}>
                <button
                    type="button"
                    className="qst-media-thumb"
                    onClick={() => setOpen(true)}
                    title="Haz clic para ampliar la imagen antes de confirmar"
                    style={{
                        background: "#ffffff",
                        border: "1px solid rgba(148,163,184,.35)",
                        borderRadius: 8,
                        padding: 6,
                        cursor: "zoom-in",
                        maxWidth: "100%",
                        display: "inline-flex",
                        justifyContent: "center",
                        alignItems: "center",
                    }}
                >
                    <img
                        src={url}
                        alt={alt}
                        loading="lazy"
                        onError={() => setFailed(true)}
                        style={{
                            display: "block",
                            maxWidth: "100%",
                            maxHeight,
                            width: "auto",
                            height: "auto",
                            objectFit: "contain",
                        }}
                    />
                </button>

                {showZoomButton && (
                    <button
                        type="button"
                        onClick={() => setOpen(true)}
                        style={{
                            background: "rgba(56, 189, 248, 0.12)",
                            border: "1px solid rgba(56, 189, 248, 0.35)",
                            color: "#38bdf8",
                            borderRadius: 6,
                            padding: "4px 10px",
                            fontSize: "0.75rem",
                            fontWeight: 600,
                            cursor: "pointer",
                            display: "inline-flex",
                            alignItems: "center",
                            gap: 6,
                        }}
                    >
                        <i className="fa-solid fa-magnifying-glass-plus"></i>
                        <span>Ver imagen ampliada</span>
                    </button>
                )}
            </div>

            {open && (
                <div
                    className="qst-modal-overlay"
                    role="dialog"
                    aria-label="Imagen ampliada"
                    onClick={() => setOpen(false)}
                    style={{ cursor: "zoom-out", zIndex: 2200, padding: 20 }}
                >
                    <div
                        onClick={(e) => e.stopPropagation()}
                        style={{
                            background: "#0f172a",
                            border: "1px solid rgba(148, 163, 184, 0.3)",
                            borderRadius: 12,
                            padding: 16,
                            maxWidth: "94vw",
                            maxHeight: "94vh",
                            display: "flex",
                            flexDirection: "column",
                            gap: 12,
                            cursor: "default",
                            boxShadow: "0 20px 50px rgba(0, 0, 0, 0.65)",
                        }}
                    >
                        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", gap: 16 }}>
                            <div>
                                <div style={{ color: "#f8fafc", fontWeight: 700, fontSize: "0.95rem" }}>
                                    <i className="fa-solid fa-magnifying-glass-plus" style={{ color: "#38bdf8", marginRight: 8 }}></i>
                                    {alt || "Vista previa ampliada del recurso visual"}
                                </div>
                                {meta && (
                                    <div style={{ color: "#94a3b8", fontSize: "0.76rem", marginTop: 2 }}>
                                        Página {meta.page_number ?? meta.page} • {meta.width}×{meta.height} px • {meta.mime_type || "imagen"}
                                        {meta.sha256 ? ` • SHA-256: ${meta.sha256.slice(0, 16)}…` : ""}
                                    </div>
                                )}
                            </div>
                            <button
                                type="button"
                                onClick={() => setOpen(false)}
                                aria-label="Cerrar vista ampliada"
                                style={{
                                    background: "rgba(239, 68, 68, 0.15)",
                                    border: "1px solid rgba(239, 68, 68, 0.4)",
                                    color: "#f87171",
                                    borderRadius: 8,
                                    padding: "6px 12px",
                                    cursor: "pointer",
                                    fontWeight: 600,
                                    fontSize: "0.8rem",
                                }}
                            >
                                <i className="fa-solid fa-xmark"></i> Cerrar (Esc)
                            </button>
                        </div>

                        <div style={{ overflow: "auto", display: "flex", justifyContent: "center", alignItems: "center", background: "#ffffff", borderRadius: 8, padding: 8 }}>
                            <img
                                src={url}
                                alt={alt}
                                style={{
                                    maxWidth: "88vw",
                                    maxHeight: "78vh",
                                    objectFit: "contain",
                                    display: "block",
                                }}
                            />
                        </div>
                    </div>
                </div>
            )}
        </>
    );
};

/**
 * Bloque visual de un estímulo:
 * 1) Si tiene recursos confirmados en media_assets (confirmed_media_assets), los muestra.
 * 2) Si tiene media_path directo, lo muestra.
 * 3) Si declara media_type != 'none' sin recurso confirmado, muestra aviso de pendiente.
 */
export const StimulusMedia = ({ stimulus }) => {
    if (!stimulus) return null;

    const confirmedAssets =
        stimulus.confirmed_media_assets ||
        stimulus.confirmedMediaAssets ||
        [];

    if (confirmedAssets.length > 0) {
        return (
            <div style={{ display: "flex", flexDirection: "column", gap: 10, marginTop: 8 }}>
                {confirmedAssets.map((asset) => (
                    <div
                        key={asset.id || asset.storage_path}
                        style={{
                            display: "flex",
                            flexDirection: "column",
                            gap: 6,
                            padding: "10px 12px",
                            borderRadius: 8,
                            background: "rgba(34, 197, 94, 0.06)",
                            border: "1px solid rgba(34, 197, 94, 0.25)",
                        }}
                    >
                        <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", flexWrap: "wrap", gap: 8 }}>
                            <span style={{ fontSize: "0.76rem", color: "#4ade80", fontWeight: 600, display: "inline-flex", alignItems: "center", gap: 6 }}>
                                <i className="fa-solid fa-circle-check"></i>
                                Recurso visual confirmado (Página {asset.page_number} • {asset.width}×{asset.height} px)
                            </span>
                        </div>
                        <MediaImage
                            path={asset.storage_path}
                            alt={stimulus.title || `Recurso visual página ${asset.page_number}`}
                            maxHeight={380}
                            meta={asset}
                        />
                    </div>
                ))}
            </div>
        );
    }

    if (stimulus.media_path) {
        return <MediaImage path={stimulus.media_path} alt={stimulus.title || "Recurso visual del estímulo"} />;
    }

    if (stimulus.media_type && stimulus.media_type !== "none") {
        return (
            <div
                className="qst-media-pending"
                style={{
                    display: "flex",
                    gap: 8,
                    alignItems: "center",
                    padding: "8px 12px",
                    marginTop: 6,
                    borderRadius: 8,
                    background: "rgba(245,158,11,.1)",
                    border: "1px dashed rgba(245,158,11,.5)",
                    color: "#fbbf24",
                    fontSize: "0.8rem",
                }}
            >
                <i className="fa-solid fa-triangle-exclamation"></i>
                <span>
                    Este estímulo incluye un recurso visual ({stimulus.media_type}) <strong>pendiente de asociación</strong>. Requiere revisión administrativa.
                </span>
            </div>
        );
    }

    return null;
};

/**
 * Imagen propia de la pregunta:
 * Muestra los recursos confirmados en media_assets (confirmed_media_assets)
 * o question_image_path si existe.
 */
export const QuestionMedia = ({ question }) => {
    if (!question) return null;

    const confirmedAssets =
        question.confirmed_media_assets ||
        question.confirmedMediaAssets ||
        [];

    if (confirmedAssets.length > 0) {
        return (
            <div style={{ display: "flex", flexDirection: "column", gap: 10, margin: "8px 0" }}>
                {confirmedAssets.map((asset) => (
                    <div
                        key={asset.id || asset.storage_path}
                        style={{
                            display: "flex",
                            flexDirection: "column",
                            gap: 6,
                            padding: "10px 12px",
                            borderRadius: 8,
                            background: "rgba(34, 197, 94, 0.06)",
                            border: "1px solid rgba(34, 197, 94, 0.25)",
                        }}
                    >
                        <span style={{ fontSize: "0.76rem", color: "#4ade80", fontWeight: 600, display: "inline-flex", alignItems: "center", gap: 6 }}>
                            <i className="fa-solid fa-circle-check"></i>
                            Imagen asociada a la pregunta (Página {asset.page_number} • {asset.width}×{asset.height} px)
                        </span>
                        <MediaImage
                            path={asset.storage_path}
                            alt={`Imagen de la pregunta #${question.order_in_group || ""}`}
                            maxHeight={340}
                            meta={asset}
                        />
                    </div>
                ))}
            </div>
        );
    }

    if (question.question_image_path) {
        return <MediaImage path={question.question_image_path} alt="Imagen de la pregunta" />;
    }

    return null;
};

/**
 * Panel administrativo para visualizar las imágenes recuperadas de un PDF,
 * ampliarlas antes de confirmar, seleccionar el estímulo o pregunta correspondiente
 * del mismo documento y confirmar o desasociar de manera controlada.
 */
export const MediaAssociationPanel = ({
    docMedia,
    extractedQuestions = [],
    onAssociationChanged,
}) => {
    const [statusFilter, setStatusFilter] = useState("all"); // "all" | "pending" | "confirmed"
    const [draftSelections, setDraftSelections] = useState({});
    const [loadingAssetId, setLoadingAssetId] = useState(null);
    const [feedback, setFeedback] = useState(null);

    const assets = useMemo(() => {
        if (!docMedia) return [];
        return docMedia.assets || docMedia.images || [];
    }, [docMedia]);

    // Lista de estímulos y preguntas del mismo documento para el selector
    const { stimuliTargets, questionTargets } = useMemo(() => {
        const backendStimuli = docMedia?.targets?.stimuli || [];
        const backendQuestions = docMedia?.targets?.questions || [];

        if (backendStimuli.length > 0 || backendQuestions.length > 0) {
            return {
                stimuliTargets: backendStimuli,
                questionTargets: backendQuestions,
            };
        }

        // Fallback a partir de extractedQuestions
        const stimMap = new Map();
        extractedQuestions.forEach((q) => {
            if (q.stimulus_id && q.stimulus && !stimMap.has(q.stimulus_id)) {
                stimMap.set(q.stimulus_id, {
                    ...q.stimulus,
                    questions: extractedQuestions.filter((item) => item.stimulus_id === q.stimulus_id),
                });
            }
        });

        return {
            stimuliTargets: Array.from(stimMap.values()),
            questionTargets: extractedQuestions,
        };
    }, [docMedia, extractedQuestions]);

    const counts = useMemo(() => {
        const total = assets.length;
        const confirmed = assets.filter((a) => a.association_status === "confirmed").length;
        const pending = total - confirmed;
        return { total, pending, confirmed };
    }, [assets]);

    const filteredAssets = useMemo(() => {
        if (statusFilter === "pending") {
            return assets.filter((a) => a.association_status !== "confirmed");
        }
        if (statusFilter === "confirmed") {
            return assets.filter((a) => a.association_status === "confirmed");
        }
        return assets;
    }, [assets, statusFilter]);

    if (!docMedia || assets.length === 0) {
        return null;
    }

    const getSelectionForAsset = (asset) => {
        if (draftSelections[asset.id]) {
            return draftSelections[asset.id];
        }
        if (asset.stimulus_id) {
            return { targetType: "stimulus", targetId: asset.stimulus_id };
        }
        if (asset.question_id) {
            return { targetType: "question", targetId: asset.question_id };
        }
        return { targetType: "stimulus", targetId: "" };
    };

    const handleTargetTypeChange = (assetId, newType) => {
        setDraftSelections((prev) => ({
            ...prev,
            [assetId]: { targetType: newType, targetId: "" },
        }));
    };

    const handleTargetIdChange = (assetId, targetType, newTargetId) => {
        setDraftSelections((prev) => ({
            ...prev,
            [assetId]: { targetType, targetId: newTargetId },
        }));
    };

    const handleConfirmAssociate = async (asset) => {
        const sel = getSelectionForAsset(asset);
        if (!sel.targetId) {
            setFeedback({
                type: "error",
                text: "Selecciona primero el estímulo o la pregunta correspondiente antes de confirmar.",
            });
            return;
        }

        setLoadingAssetId(asset.id);
        setFeedback(null);

        try {
            const res = await associateMediaAsset(asset.id, {
                target_type: sel.targetType,
                target_id: sel.targetId,
            });

            if (res?.success) {
                setFeedback({
                    type: "success",
                    text: res.message || "Asociación confirmada exitosamente.",
                });
                if (onAssociationChanged) {
                    await onAssociationChanged();
                }
            } else {
                setFeedback({
                    type: "error",
                    text: res?.message || "No se pudo confirmar la asociación.",
                });
            }
        } catch (err) {
            const msg =
                err.response?.data?.message ||
                err.message ||
                "Error al asociar el recurso visual.";
            setFeedback({ type: "error", text: msg });
        } finally {
            setLoadingAssetId(null);
        }
    };

    const handleDisassociate = async (asset) => {
        setLoadingAssetId(asset.id);
        setFeedback(null);

        try {
            const res = await disassociateMediaAsset(asset.id);
            if (res?.success) {
                setDraftSelections((prev) => ({
                    ...prev,
                    [asset.id]: { targetType: "stimulus", targetId: "" },
                }));
                setFeedback({
                    type: "success",
                    text: res.message || "Recurso desasociado y devuelto a estado pendiente.",
                });
                if (onAssociationChanged) {
                    await onAssociationChanged();
                }
            } else {
                setFeedback({
                    type: "error",
                    text: res?.message || "No se pudo desasociar el recurso.",
                });
            }
        } catch (err) {
            const msg =
                err.response?.data?.message ||
                err.message ||
                "Error al desasociar el recurso visual.";
            setFeedback({ type: "error", text: msg });
        } finally {
            setLoadingAssetId(null);
        }
    };

    return (
        <div className="qst-stimulus-container" style={{ border: "1px solid rgba(56, 189, 248, 0.3)" }}>
            <div className="qst-stimulus-header" style={{ flexWrap: "wrap", gap: 12 }}>
                <div className="qst-stimulus-title-box">
                    <i className="fa-solid fa-images" style={{ color: "#38bdf8" }}></i>
                    <div>
                        <h3 className="qst-stimulus-title">
                            Panel Administrativo de Recursos Visuales ({counts.total})
                        </h3>
                        <span style={{ fontSize: "0.78rem", color: "#94a3b8" }}>
                            Amplía cada imagen para verificar su contenido, selecciona el estímulo o pregunta del cuadernillo y confirma su asociación.
                        </span>
                    </div>
                </div>

                <div style={{ display: "flex", alignItems: "center", gap: 8, flexWrap: "wrap" }}>
                    <button
                        type="button"
                        onClick={() => setStatusFilter("all")}
                        style={{
                            padding: "5px 12px",
                            borderRadius: 6,
                            fontSize: "0.78rem",
                            fontWeight: 600,
                            cursor: "pointer",
                            border: statusFilter === "all" ? "1px solid #38bdf8" : "1px solid rgba(148,163,184,0.25)",
                            background: statusFilter === "all" ? "rgba(56,189,248,0.16)" : "rgba(15,23,42,0.5)",
                            color: statusFilter === "all" ? "#38bdf8" : "#cbd5e1",
                        }}
                    >
                        Todos ({counts.total})
                    </button>
                    <button
                        type="button"
                        onClick={() => setStatusFilter("pending")}
                        style={{
                            padding: "5px 12px",
                            borderRadius: 6,
                            fontSize: "0.78rem",
                            fontWeight: 600,
                            cursor: "pointer",
                            border: statusFilter === "pending" ? "1px solid #f59e0b" : "1px solid rgba(148,163,184,0.25)",
                            background: statusFilter === "pending" ? "rgba(245,158,11,0.16)" : "rgba(15,23,42,0.5)",
                            color: statusFilter === "pending" ? "#fbbf24" : "#cbd5e1",
                        }}
                    >
                        Pendientes ({counts.pending})
                    </button>
                    <button
                        type="button"
                        onClick={() => setStatusFilter("confirmed")}
                        style={{
                            padding: "5px 12px",
                            borderRadius: 6,
                            fontSize: "0.78rem",
                            fontWeight: 600,
                            cursor: "pointer",
                            border: statusFilter === "confirmed" ? "1px solid #22c55e" : "1px solid rgba(148,163,184,0.25)",
                            background: statusFilter === "confirmed" ? "rgba(34,197,94,0.16)" : "rgba(15,23,42,0.5)",
                            color: statusFilter === "confirmed" ? "#4ade80" : "#cbd5e1",
                        }}
                    >
                        Confirmados ({counts.confirmed})
                    </button>
                </div>
            </div>

            {feedback && (
                <div
                    className={`qst-alert ${feedback.type === "success" ? "qst-alert--success" : "qst-alert--error"}`}
                    style={{ margin: "12px 12px 0" }}
                >
                    <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                        <i className={`fa-solid ${feedback.type === "success" ? "fa-circle-check" : "fa-triangle-exclamation"}`}></i>
                        <span>{feedback.text}</span>
                    </div>
                    <button
                        type="button"
                        className="qst-alert-close"
                        onClick={() => setFeedback(null)}
                        aria-label="Cerrar aviso"
                    >
                        <i className="fa-solid fa-xmark"></i>
                    </button>
                </div>
            )}

            <div
                style={{
                    display: "grid",
                    gridTemplateColumns: "repeat(auto-fill, minmax(310px, 1fr))",
                    gap: 16,
                    padding: 14,
                }}
            >
                {filteredAssets.map((asset) => {
                    const pageNum = asset.page_number ?? asset.page;
                    const isConfirmed = asset.association_status === "confirmed";
                    const isBusy = loadingAssetId === asset.id;
                    const sel = getSelectionForAsset(asset);

                    const selectedStimulusPreview =
                        sel.targetType === "stimulus" && sel.targetId
                            ? stimuliTargets.find((s) => s.id === sel.targetId)
                            : null;
                    const selectedQuestionPreview =
                        sel.targetType === "question" && sel.targetId
                            ? questionTargets.find((q) => q.id === sel.targetId)
                            : null;

                    return (
                        <div
                            key={asset.id || asset.storage_path}
                            style={{
                                display: "flex",
                                flexDirection: "column",
                                justifyContent: "space-between",
                                gap: 10,
                                padding: 14,
                                borderRadius: 10,
                                background: "rgba(15, 23, 42, 0.65)",
                                border: isConfirmed
                                    ? "1px solid rgba(34, 197, 94, 0.4)"
                                    : "1px solid rgba(148, 163, 184, 0.22)",
                            }}
                        >
                            {/* Encabezado de metadatos y estado */}
                            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", gap: 8, flexWrap: "wrap" }}>
                                <span style={{ fontSize: "0.8rem", fontWeight: 700, color: "#f8fafc" }}>
                                    <i className="fa-regular fa-file-image" style={{ color: "#38bdf8", marginRight: 6 }}></i>
                                    Página {pageNum} • {asset.width}×{asset.height}px
                                </span>
                                {isConfirmed ? (
                                    <span
                                        style={{
                                            fontSize: "0.72rem",
                                            fontWeight: 700,
                                            padding: "3px 8px",
                                            borderRadius: 999,
                                            background: "rgba(34, 197, 94, 0.15)",
                                            color: "#4ade80",
                                            border: "1px solid rgba(34, 197, 94, 0.35)",
                                        }}
                                    >
                                        <i className="fa-solid fa-check"></i> Confirmado
                                    </span>
                                ) : (
                                    <span
                                        style={{
                                            fontSize: "0.72rem",
                                            fontWeight: 700,
                                            padding: "3px 8px",
                                            borderRadius: 999,
                                            background: "rgba(245, 158, 11, 0.15)",
                                            color: "#fbbf24",
                                            border: "1px solid rgba(245, 158, 11, 0.35)",
                                        }}
                                    >
                                        <i className="fa-solid fa-clock"></i> Pendiente
                                    </span>
                                )}
                            </div>

                            {/* Miniatura y botón para ampliar */}
                            {asset.storage_path ? (
                                <MediaImage
                                    path={asset.storage_path}
                                    alt={`Recurso visual de la página ${pageNum} (${asset.width}×${asset.height}px)`}
                                    maxHeight={185}
                                    meta={asset}
                                    showZoomButton={true}
                                />
                            ) : (
                                <div className="qst-media-missing">Formato no soportado.</div>
                            )}

                            {/* Huella SHA-256 y formato */}
                            <div style={{ fontSize: "0.72rem", color: "#64748b", fontFamily: "monospace" }}>
                                {asset.mime_type || "image"} • SHA-256: {asset.sha256 ? `${asset.sha256.slice(0, 14)}…` : "n/a"}
                            </div>

                            {/* Estado confirmado actual */}
                            {isConfirmed && (
                                <div
                                    style={{
                                        padding: "8px 10px",
                                        borderRadius: 8,
                                        background: "rgba(34, 197, 94, 0.08)",
                                        border: "1px solid rgba(34, 197, 94, 0.25)",
                                        fontSize: "0.78rem",
                                        color: "#e2e8f0",
                                    }}
                                >
                                    <div style={{ fontWeight: 700, color: "#4ade80", marginBottom: 3 }}>
                                        <i className="fa-solid fa-link"></i> Asociado actualmente a:
                                    </div>
                                    {asset.stimulus ? (
                                        <div>
                                            <strong>Estímulo:</strong> {asset.stimulus.title}
                                        </div>
                                    ) : asset.question ? (
                                        <div>
                                            <strong>Pregunta #{asset.question.order_in_group || "—"}:</strong>{" "}
                                            {(asset.question.statement || "").slice(0, 95)}…
                                        </div>
                                    ) : (
                                        <div>Registro confirmado</div>
                                    )}
                                </div>
                            )}

                            {/* Controles de selección y confirmación / desasociación */}
                            {asset.id && (
                                <div style={{ display: "flex", flexDirection: "column", gap: 8, marginTop: 4 }}>
                                    <div style={{ display: "flex", gap: 6 }}>
                                        <button
                                            type="button"
                                            disabled={isBusy}
                                            onClick={() => handleTargetTypeChange(asset.id, "stimulus")}
                                            style={{
                                                flex: 1,
                                                padding: "5px 8px",
                                                borderRadius: 6,
                                                fontSize: "0.75rem",
                                                fontWeight: 600,
                                                cursor: "pointer",
                                                border:
                                                    sel.targetType === "stimulus"
                                                        ? "1px solid #38bdf8"
                                                        : "1px solid rgba(148,163,184,0.25)",
                                                background:
                                                    sel.targetType === "stimulus"
                                                        ? "rgba(56, 189, 248, 0.15)"
                                                        : "rgba(15, 23, 42, 0.6)",
                                                color: sel.targetType === "stimulus" ? "#38bdf8" : "#94a3b8",
                                            }}
                                        >
                                            <i className="fa-solid fa-book-open"></i> Estímulo ({stimuliTargets.length})
                                        </button>
                                        <button
                                            type="button"
                                            disabled={isBusy}
                                            onClick={() => handleTargetTypeChange(asset.id, "question")}
                                            style={{
                                                flex: 1,
                                                padding: "5px 8px",
                                                borderRadius: 6,
                                                fontSize: "0.75rem",
                                                fontWeight: 600,
                                                cursor: "pointer",
                                                border:
                                                    sel.targetType === "question"
                                                        ? "1px solid #a855f7"
                                                        : "1px solid rgba(148,163,184,0.25)",
                                                background:
                                                    sel.targetType === "question"
                                                        ? "rgba(168, 85, 247, 0.15)"
                                                        : "rgba(15, 23, 42, 0.6)",
                                                color: sel.targetType === "question" ? "#c084fc" : "#94a3b8",
                                            }}
                                        >
                                            <i className="fa-solid fa-circle-question"></i> Pregunta ({questionTargets.length})
                                        </button>
                                    </div>

                                    {sel.targetType === "stimulus" ? (
                                        <select
                                            className="qst-form-select"
                                            value={sel.targetId}
                                            disabled={isBusy}
                                            onChange={(e) =>
                                                handleTargetIdChange(asset.id, "stimulus", e.target.value)
                                            }
                                            style={{ fontSize: "0.78rem", padding: "6px 8px" }}
                                        >
                                            <option value="">-- Seleccionar estímulo del documento --</option>
                                            {stimuliTargets.map((s) => {
                                                const qNums = (s.questions || [])
                                                    .map((q) => q.order_in_group)
                                                    .filter(Boolean)
                                                    .join(", ");
                                                const mediaTag =
                                                    s.media_type && s.media_type !== "none"
                                                        ? `[${s.media_type.toUpperCase()}] `
                                                        : "";
                                                return (
                                                    <option key={s.id} value={s.id}>
                                                        {mediaTag}
                                                        {s.title || "Estímulo"}
                                                        {qNums ? ` (Preg. ${qNums})` : ""}
                                                    </option>
                                                );
                                            })}
                                        </select>
                                    ) : (
                                        <select
                                            className="qst-form-select"
                                            value={sel.targetId}
                                            disabled={isBusy}
                                            onChange={(e) =>
                                                handleTargetIdChange(asset.id, "question", e.target.value)
                                            }
                                            style={{ fontSize: "0.78rem", padding: "6px 8px" }}
                                        >
                                            <option value="">-- Seleccionar pregunta del documento --</option>
                                            {questionTargets.map((q) => (
                                                <option key={q.id} value={q.id}>
                                                    Pregunta #{q.order_in_group || "—"} —{" "}
                                                    {(q.statement || "").slice(0, 65)}...
                                                </option>
                                            ))}
                                        </select>
                                    )}

                                    {/* Resumen breve del destino seleccionado antes de confirmar */}
                                    {selectedStimulusPreview && (
                                        <div
                                            style={{
                                                fontSize: "0.74rem",
                                                color: "#cbd5e1",
                                                background: "rgba(30, 41, 59, 0.7)",
                                                padding: "6px 8px",
                                                borderRadius: 6,
                                                borderLeft: "2px solid #38bdf8",
                                            }}
                                        >
                                            <strong>{selectedStimulusPreview.title}</strong>
                                            {selectedStimulusPreview.instructions && (
                                                <div style={{ color: "#94a3b8", marginTop: 2 }}>
                                                    {selectedStimulusPreview.instructions}
                                                </div>
                                            )}
                                        </div>
                                    )}

                                    {selectedQuestionPreview && (
                                        <div
                                            style={{
                                                fontSize: "0.74rem",
                                                color: "#cbd5e1",
                                                background: "rgba(30, 41, 59, 0.7)",
                                                padding: "6px 8px",
                                                borderRadius: 6,
                                                borderLeft: "2px solid #a855f7",
                                            }}
                                        >
                                            <strong>Pregunta #{selectedQuestionPreview.order_in_group}:</strong>{" "}
                                            {(selectedQuestionPreview.statement || "").slice(0, 120)}
                                        </div>
                                    )}

                                    <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
                                        <button
                                            type="button"
                                            disabled={isBusy || !sel.targetId}
                                            onClick={() => handleConfirmAssociate(asset)}
                                            style={{
                                                flex: 1,
                                                padding: "7px 10px",
                                                borderRadius: 7,
                                                fontSize: "0.78rem",
                                                fontWeight: 700,
                                                cursor: isBusy || !sel.targetId ? "not-allowed" : "pointer",
                                                opacity: isBusy || !sel.targetId ? 0.55 : 1,
                                                background: "linear-gradient(135deg, #22c55e 0%, #16a34a 100%)",
                                                color: "#ffffff",
                                                border: "none",
                                                display: "inline-flex",
                                                alignItems: "center",
                                                justifyContent: "center",
                                                gap: 6,
                                            }}
                                        >
                                            <i className="fa-solid fa-check"></i>
                                            <span>{isConfirmed ? "Actualizar asociación" : "Confirmar asociación"}</span>
                                        </button>

                                        {isConfirmed && (
                                            <button
                                                type="button"
                                                disabled={isBusy}
                                                onClick={() => handleDisassociate(asset)}
                                                title="Desasociar y volver a estado pendiente sin borrar el archivo"
                                                style={{
                                                    padding: "7px 10px",
                                                    borderRadius: 7,
                                                    fontSize: "0.78rem",
                                                    fontWeight: 600,
                                                    cursor: isBusy ? "not-allowed" : "pointer",
                                                    background: "rgba(239, 68, 68, 0.14)",
                                                    color: "#f87171",
                                                    border: "1px solid rgba(239, 68, 68, 0.35)",
                                                    display: "inline-flex",
                                                    alignItems: "center",
                                                    gap: 6,
                                                }}
                                            >
                                                <i className="fa-solid fa-link-slash"></i>
                                                <span>Desasociar</span>
                                            </button>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
};
