import useExamStore from "../../../stores/useExamStore";

/**
 * Subcomponente PracticeHeader
 * Elimina prop drilling de `currentIndex`: lo lee directamente del store useExamStore.
 */
const PracticeHeader = ({
    subjectName = "Área ICFES",
    topicName = "",
    totalQuestions = 0,
    secondsElapsed = 0,
    formatTimer,
}) => {
    // Selector atómico de Zustand: solo se suscribe al índice de la pregunta actual
    const currentIndex = useExamStore((state) => state.currentIndex);

    const progressPercentage =
        totalQuestions > 0
            ? Math.round(((currentIndex + 1) / totalQuestions) * 100)
            : 0;

    return (
        <>
            <section className="quiz-top">
                <div className="quiz-top__left">
                    <span className="quiz-top__subject">
                        PRÁCTICA · {subjectName.toUpperCase()}
                        {topicName ? ` · ${topicName}` : ""}
                    </span>
                    <h2>
                        Pregunta {currentIndex + 1} de {totalQuestions}
                    </h2>
                </div>

                <div className="quiz-top__timer">
                    <i className="fa-regular fa-clock"></i>
                    <span>
                        {typeof formatTimer === "function"
                            ? formatTimer(secondsElapsed)
                            : secondsElapsed}
                    </span>
                </div>
            </section>

            <div className="progress">
                <div
                    className="progress__fill"
                    style={{ width: `${progressPercentage}%` }}
                ></div>
            </div>
        </>
    );
};

export default PracticeHeader;
