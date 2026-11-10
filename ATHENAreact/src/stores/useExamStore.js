import { create } from "zustand";

export const useExamStore = create((set) => ({
    currentIndex: 0,
    answers: {},
    feedbacks: {},

    // Cambiar el índice posición de la pregunta actual
    setCurrentIndex: (index) =>
        set((state) => ({
            currentIndex: typeof index === "function" ? index(state.currentIndex) : index,
        })),

    // Guardar o actualizar la respuesta seleccionada de una pregunta
    setAnswer: (questionId, optionId) =>
        set((state) => ({
            answers: {
                ...state.answers,
                [questionId]: optionId,
            },
        })),

    // Guardar la retroalimentación del servidor (es correcto, clave oficial, explicación)
    setFeedback: (questionId, feedback) =>
        set((state) => ({
            feedbacks: {
                ...state.feedbacks,
                [questionId]: feedback,
            },
        })),

    // Inicializa el estado al cargar una práctica
    initPractice: (initialAnswers = {}, initialIndex = 0, initialFeedbacks = {}) =>
        set({
            answers: initialAnswers,
            currentIndex: initialIndex,
            feedbacks: initialFeedbacks,
        }),

    // Limpia el estado al finalizar, abandonar o cambiar de práctica
    resetPractice: () =>
        set({
            currentIndex: 0,
            answers: {},
            feedbacks: {},
        }),
}));

export default useExamStore;
