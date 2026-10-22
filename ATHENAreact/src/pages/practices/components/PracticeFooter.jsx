import { useShallow } from "zustand/react/shallow";
import useExamStore from "../../../stores/useExamStore";

/**
 * Subcomponente PracticeFooter
 * Elimina prop drilling de navegación:
 * Obtiene `currentIndex`, `setCurrentIndex` y `resetPractice` directamente de useExamStore con useShallow.
 */
const PracticeFooter = ({
    totalQuestions = 0,
    saving = false,
    finishing = false,
    onFinish,
}) => {
    // Suscripción agrupada optimizada con useShallow para múltiples valores del store
    const { currentIndex, setCurrentIndex } = useExamStore(
        useShallow((state) => ({
            currentIndex: state.currentIndex,
            setCurrentIndex: state.setCurrentIndex,
        }))
    );

    const handlePrevious = () => {
        if (currentIndex > 0) {
            setCurrentIndex(currentIndex - 1);
            window.scrollTo({ top: 0, behavior: "smooth" });
        }
    };

    const handleNext = () => {
        if (currentIndex < totalQuestions - 1) {
            setCurrentIndex(currentIndex + 1);
            window.scrollTo({ top: 0, behavior: "smooth" });
        }
    };

    return (
        <section
            className="quiz-footer"
            style={{
                display: "flex",
                justifyContent: "space-between",
                alignItems: "center",
                marginTop: "24px",
            }}
        >
            <button
                type="button"
                className="footer-btn footer-btn--ghost"
                onClick={handlePrevious}
                disabled={currentIndex === 0 || saving || finishing}
            >
                <i className="fas fa-chevron-left"></i>
                Anterior
            </button>

            <div style={{ display: "flex", gap: "10px" }}>
                <button
                    type="button"
                    className="footer-btn footer-btn--ghost"
                    onClick={onFinish}
                    disabled={finishing}
                    style={{ borderColor: "#ef4444", color: "#fca5a5" }}
                >
                    <i
                        className="fas fa-flag-checkered"
                        style={{ marginRight: "6px" }}
                    ></i>
                    Finalizar Práctica
                </button>

                {currentIndex < totalQuestions - 1 ? (
                    <button
                        type="button"
                        className="footer-btn footer-btn--primary"
                        onClick={handleNext}
                        disabled={saving || finishing}
                    >
                        Siguiente
                        <i className="fas fa-chevron-right"></i>
                    </button>
                ) : (
                    <button
                        type="button"
                        className="footer-btn footer-btn--primary"
                        onClick={onFinish}
                        disabled={finishing}
                        style={{ background: "#10b981" }}
                    >
                        {finishing ? "Finalizando..." : "Ver Resultados"}
                        <i
                            className="fas fa-check"
                            style={{ marginLeft: "6px" }}
                        ></i>
                    </button>
                )}
            </div>
        </section>
    );
};

export default PracticeFooter;
