import { create } from "zustand";

export const useExamStore = create((set) => ({
    currentIndex: 0,
    answers: {},

    // Cambiar el indice posicion de la pregunta actual
    setCurrentIndex: (index) =>
        set({
            currentIndex: index,
        }),

    //Guardar o actualiza la respuesta de una pregunta
    setAnswer: (questionId, optionId) =>
        set((state) => ({
            answers: {
                ...state.answers,
                [questionId]: optionId,
            },
        })),

    //Inicializa el estado al cargar una practica
    initPractice: (initialAnswers = {}, initialIndex = 0) =>
        set({
            answers: initialAnswers,
            currentIndex: initialIndex,
        }),

    //Limpia el estado al finalizar, abandonar o cambiar de practica
    resetPractice: () =>
        set({
            currentIndex: 0,
            answers: {},
        }),
}));

export default useExamStore;
