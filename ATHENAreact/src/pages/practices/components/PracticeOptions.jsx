import useExamStore from "../../../stores/useExamStore";

/**
 * Subcomponente PracticeOptions
 * Elimina prop drilling de `answers` y `setAnswer`:
 * Obtiene la respuesta de la pregunta actual y la acción actualizadora directamente de useExamStore.
 */
const PracticeOptions = ({
    question,
    options = [],
    disabled = false,
    resolveMediaUrl,
    onOptionSelected,
}) => {
    // Selector atómico: se suscribe exclusivamente a la respuesta seleccionada de esta pregunta
    const selectedOptionId = useExamStore((state) =>
        question ? state.answers[question.id] : null
    );
    const setAnswer = useExamStore((state) => state.setAnswer);

    const getOptionText = (opt) => {
        return opt?.option_text || opt?.text || opt?.content || "";
    };

    const handleClickOption = (option) => {
        if (!question || disabled || option?.id == null) return;

        // Actualización inmediata en el store global de Zustand
        setAnswer(question.id, option.id);

        // Notifica al padre para persistir asíncronamente en backend
        if (typeof onOptionSelected === "function") {
            onOptionSelected(option);
        }
    };

    return (
        <div className="options">
            {options.map((opt) => {
                const optionLetter = opt.option_letter || "A";
                const isSelected =
                    selectedOptionId != null &&
                    String(selectedOptionId) === String(opt.id);

                return (
                    <button
                        key={opt.id}
                        type="button"
                        className={`option ${isSelected ? "option--selected" : ""}`}
                        onClick={() => handleClickOption(opt)}
                        disabled={disabled}
                    >
                        <span className="option__letter">{optionLetter}</span>
                        <div style={{ textAlign: "left", width: "100%" }}>
                            <span className="option__text">
                                {getOptionText(opt)}
                            </span>
                            {opt.option_image_url && (
                                <div className="option-image-box">
                                    <img
                                        src={
                                            typeof resolveMediaUrl === "function"
                                                ? resolveMediaUrl(opt.option_image_url)
                                                : opt.option_image_url
                                        }
                                        alt={`Opción ${optionLetter}`}
                                    />
                                </div>
                            )}
                        </div>
                    </button>
                );
            })}
        </div>
    );
};

export default PracticeOptions;
