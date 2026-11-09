import { useState, useEffect } from "react";
import { getMediaUrl } from "../../api/documents";

/**
 * Imagen servida por el backend con proporciones originales, ampliable (lightbox)
 * y con fallback si el recurso no carga (evita imágenes rotas).
 */
export const MediaImage = ({ path, alt, maxHeight = 360 }) => {
    const [failed, setFailed] = useState(false);
    const [open, setOpen] = useState(false);
    const url = getMediaUrl(path);

    useEffect(() => {
        if (!open) return undefined;
        const onKey = (e) => e.key === "Escape" && setOpen(false);
        window.addEventListener("keydown", onKey);
        return () => window.removeEventListener("keydown", onKey);
    }, [open]);

    if (!url || failed) {
        return (
            <div className="qst-media-missing">
                <i className="fa-solid fa-image"></i> No se pudo cargar el recurso visual.
            </div>
        );
    }

    return (
        <>
            <button
                type="button"
                className="qst-media-thumb"
                onClick={() => setOpen(true)}
                title="Ampliar imagen"
                style={{ background: "#fff", border: "1px solid rgba(148,163,184,.3)", borderRadius: 8, padding: 4, cursor: "zoom-in" }}
            >
                <img
                    src={url}
                    alt={alt}
                    loading="lazy"
                    onError={() => setFailed(true)}
                    style={{ display: "block", maxWidth: "100%", maxHeight, width: "auto", height: "auto", objectFit: "contain" }}
                />
            </button>
            {open && (
                <div
                    className="qst-modal-overlay"
                    role="dialog"
                    aria-label="Imagen ampliada"
                    onClick={() => setOpen(false)}
                    style={{ cursor: "zoom-out", zIndex: 2000 }}
                >
                    <img
                        src={url}
                        alt={alt}
                        onClick={(e) => e.stopPropagation()}
                        style={{ maxWidth: "92vw", maxHeight: "92vh", objectFit: "contain", background: "#fff", borderRadius: 8 }}
                    />
                </div>
            )}
        </>
    );
};

/**
 * Bloque visual de un estímulo: muestra la imagen asociada o, si el estímulo
 * declara contenido visual y no tiene recurso asignado, un aviso de pendiente.
 */
export const StimulusMedia = ({ stimulus }) => {
    if (!stimulus) return null;
    if (stimulus.media_path) {
        return <MediaImage path={stimulus.media_path} alt={stimulus.title || "Recurso visual del estímulo"} />;
    }
    if (stimulus.media_type && stimulus.media_type !== "none") {
        return (
            <div
                className="qst-media-pending"
                style={{ display: "flex", gap: 8, alignItems: "center", padding: "8px 12px", marginTop: 6, borderRadius: 8, background: "rgba(245,158,11,.1)", border: "1px dashed rgba(245,158,11,.5)", color: "#fbbf24", fontSize: "0.8rem" }}
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

/** Imagen propia de la pregunta (question_image_path), si existe. */
export const QuestionMedia = ({ question }) =>
    question?.question_image_path ? (
        <MediaImage path={question.question_image_path} alt="Imagen de la pregunta" />
    ) : null;

