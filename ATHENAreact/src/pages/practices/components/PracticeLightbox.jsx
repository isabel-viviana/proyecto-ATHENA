import { useEffect } from "react";

export const PracticeLightbox = ({ isOpen, onClose, src, alt, caption }) => {
    useEffect(() => {
        if (!isOpen) return;
        const handleKeyDown = (e) => {
            if (e.key === "Escape") onClose();
        };
        window.addEventListener("keydown", handleKeyDown);
        return () => window.removeEventListener("keydown", handleKeyDown);
    }, [isOpen, onClose]);

    if (!isOpen || !src) return null;

    return (
        <div
            className="practice-lightbox-overlay"
            onClick={onClose}
            style={{
                position: "fixed",
                top: 0,
                left: 0,
                right: 0,
                bottom: 0,
                backgroundColor: "rgba(10, 15, 30, 0.92)",
                zIndex: 9999,
                display: "flex",
                flexDirection: "column",
                alignItems: "center",
                justifyContent: "center",
                padding: "20px",
                cursor: "zoom-out",
            }}
        >
            <div
                onClick={(e) => e.stopPropagation()}
                style={{
                    position: "relative",
                    maxWidth: "92vw",
                    maxHeight: "90vh",
                    display: "flex",
                    flexDirection: "column",
                    alignItems: "center",
                    cursor: "default",
                }}
            >
                <div
                    style={{
                        width: "100%",
                        display: "flex",
                        justifyContent: "space-between",
                        alignItems: "center",
                        marginBottom: "12px",
                        color: "#f8fafc",
                    }}
                >
                    <span style={{ fontSize: "14px", fontWeight: 600, color: "#38bdf8", display: "inline-flex", alignItems: "center", gap: "6px" }}>
                        <i className="fas fa-magnifying-glass-plus"></i> Vista Ampliada del Recurso
                    </span>
                    <button
                        type="button"
                        onClick={onClose}
                        style={{
                            background: "rgba(239, 68, 68, 0.2)",
                            border: "1px solid rgba(239, 68, 68, 0.4)",
                            color: "#f87171",
                            padding: "6px 14px",
                            borderRadius: "8px",
                            cursor: "pointer",
                            fontSize: "13px",
                            fontWeight: 600,
                            display: "inline-flex",
                            alignItems: "center",
                            gap: "6px",
                        }}
                    >
                        <i className="fas fa-times"></i> Cerrar (Esc)
                    </button>
                </div>

                <div
                    style={{
                        background: "#ffffff",
                        padding: "8px",
                        borderRadius: "10px",
                        boxShadow: "0 20px 60px rgba(0, 0, 0, 0.8)",
                        overflow: "auto",
                        maxWidth: "100%",
                        maxHeight: "80vh",
                        display: "flex",
                        justifyContent: "center",
                    }}
                >
                    <img
                        src={src}
                        alt={alt || "Recurso ampliado"}
                        style={{
                            maxWidth: "100%",
                            maxHeight: "76vh",
                            objectFit: "contain",
                            display: "block",
                        }}
                    />
                </div>

                {caption && (
                    <p style={{ marginTop: "10px", color: "#94a3b8", fontSize: "13px", textAlign: "center" }}>
                        {caption}
                    </p>
                )}
            </div>
        </div>
    );
};

export default PracticeLightbox;

