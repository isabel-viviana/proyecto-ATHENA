import useExamStore from "../../../stores/useExamStore";
import { getStudentMediaUrl } from "../../../api/studentQuestions";

/**
 * Subcomponente PracticeOptions
 * Renderiza las opciones A, B, C y D de la pregunta.
 * Protegido contra filtración de claves: no conoce 'is_correct' hasta que el backend responde.
 * Muestra retroalimentación visual inmediata tras confirmar.
 */
const PracticeOptions = ({
    question,
    options = [],
    disabled = false,
    onOptionSelected,
}) => {
    // Respuesta seleccionada y retroalimentación desde useExamStore
    const selectedOptionId = useExamStore((state) =>
        question ? state.answers[question.id] : null
    );
    const feedback = useExamStore((state) =>
        question ? state.feedbacks[question.id] : null
    );
    const setAnswer = useExamStore((state) => state.setAnswer);

    const isConfirmed = Boolean(feedback);

    const getOptionText = (opt) => {
        return opt?.option_text || opt?.text || opt?.content || "";
    };

    const handleClickOption = (option) => {
        if (!question || disabled || isConfirmed || option?.id == null) return;

        // Actualización inmediata en el store global de Zustand
        setAnswer(question.id, option.id);

        if (typeof onOptionSelected === "function") {
            onOptionSelected(option);
        }
    };

    return (
        <div className="options" style={{ display: "flex", flexDirection: "column", gap: "12px", marginTop: "16px" }}>
            {options.map((opt) => {
                const optionLetter = opt.option_letter || "A";
                const isSelected =
                    selectedOptionId != null &&
                    String(selectedOptionId) === String(opt.id);

                // Estados de retroalimentación oficial devuelta por el servidor
                let feedbackStyle = {};
                let feedbackBadge = null;

                if (isConfirmed) {
                    const isOfficialCorrect =
                        feedback.correct_option_id === opt.id ||
                        feedback.correct_option_letter === optionLetter;

                    if (isOfficialCorrect) {
                        feedbackStyle = {
                            borderColor: "#22c55e",
                            backgroundColor: "rgba(34, 197, 94, 0.16)",
                            boxShadow: "0 0 0 2px rgba(34, 197, 94, 0.35)",
                        };
                        feedbackBadge = (
                            <span style={{ color: "#22c55e", fontWeight: 700, fontSize: "12px", marginLeft: "auto", display: "inline-flex", alignItems: "center", gap: "4px" }}>
                                <i className="fas fa-check-circle"></i> Correcta
                            </span>
                        );
                    } else if (isSelected && !feedback.is_correct) {
                        feedbackStyle = {
                            borderColor: "#ef4444",
                            backgroundColor: "rgba(239, 68, 68, 0.16)",
                            boxShadow: "0 0 0 2px rgba(239, 68, 68, 0.35)",
                        };
                        feedbackBadge = (
                            <span style={{ color: "#ef4444", fontWeight: 700, fontSize: "12px", marginLeft: "auto", display: "inline-flex", alignItems: "center", gap: "4px" }}>
                                <i className="fas fa-times-circle"></i> Tu elección
                            </span>
                        );
                    }
                }

                return (
                    <button
                        key={opt.id}
                        type="button"
                        className={`option ${isSelected ? "option--selected" : ""}`}
                        onClick={() => handleClickOption(opt)}
                        disabled={disabled || isConfirmed}
                        style={{
                            ...feedbackStyle,
                            cursor: isConfirmed ? "default" : "pointer",
                            transition: "all 0.2s ease",
                        }}
                    >
                        <span
                            className="option__letter"
                            style={
                                isConfirmed && (feedback.correct_option_letter === optionLetter)
                                    ? { backgroundColor: "#22c55e", color: "#ffffff" }
                                    : isConfirmed && isSelected && !feedback.is_correct
                                    ? { backgroundColor: "#ef4444", color: "#ffffff" }
                                    : {}
                            }
                        >
                            {optionLetter}
                        </span>
                        <div style={{ textAlign: "left", width: "100%", display: "flex", alignItems: "center", justifyContent: "space-between" }}>
                            <span className="option__text" style={{ flex: 1, paddingRight: "10px" }}>
                                {getOptionText(opt)}
                            </span>
                            {feedbackBadge}
                        </div>
                        {opt.option_image_path && (
                            <div className="option-image-box" style={{ marginTop: "8px" }}>
                                <img
                                    src={getStudentMediaUrl(opt.option_image_path)}
                                    alt={`Opción ${optionLetter}`}
                                    style={{ maxWidth: "100%", maxHeight: "140px", borderRadius: "6px" }}
                                />
                            </div>
                        )}
                    </button>
                );
            })}
        </div>
    );
};

export default PracticeOptions;
