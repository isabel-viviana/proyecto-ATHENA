import { create } from "zustand";
import { getProgressDashboard } from "../api/progress.js";

/**
 * Normaliza los datos provenientes del endpoint oficial /api/v1/student/progress
 * de ATHENA Question Bank para consumo directo en Dashboard, Estadísticas y Diagnóstico.
 */
const normalizeProgressPayload = (rawData) => {
    if (!rawData || typeof rawData !== "object") return null;

    const summary = rawData.summary || {};
    const bySubject = Array.isArray(rawData.by_subject) ? rawData.by_subject : [];
    const byTopic = Array.isArray(rawData.by_topic) ? rawData.by_topic : [];
    const weakTopics = Array.isArray(rawData.weak_topics)
        ? rawData.weak_topics
        : Array.isArray(rawData.temas_debiles)
        ? rawData.temas_debiles
        : [];
    const strongTopics = Array.isArray(rawData.strong_topics)
        ? rawData.strong_topics
        : Array.isArray(rawData.temas_fuertes)
        ? rawData.temas_fuertes
        : [];
    const dailyActivity = Array.isArray(rawData.daily_activity) ? rawData.daily_activity : [];
    const academicAnalysis = rawData.academic_analysis || {};
    const recentExams = Array.isArray(rawData.recent_exams)
        ? rawData.recent_exams
        : Array.isArray(rawData.ultimos_simulacros)
        ? rawData.ultimos_simulacros
        : [];
    const recentActivity = Array.isArray(rawData.recent_activity) ? rawData.recent_activity : [];

    const accuracy = Number(summary.accuracy ?? rawData.resumen_general?.porcentaje_global_aciertos ?? 0);
    const completedExamsCount = Number(
        summary.completed_exams_count ??
            rawData.resumen_general?.total_simulacros_completados ??
            recentExams.length ??
            0
    );
    const practiceSessionsCount = Number(
        summary.practice_sessions_count ??
            rawData.resumen_general?.total_practicas_realizadas ??
            0
    );
    const practiceQuestionsAnswered = Number(
        summary.practice_questions_answered ??
            rawData.resumen_general?.total_preguntas_practica ??
            0
    );
    const totalAnswered = Number(
        summary.total_answered ?? rawData.resumen_general?.total_preguntas_respondidas ?? 0
    );
    const correctCount = Number(
        summary.correct_count ?? rawData.resumen_general?.total_correctas ?? 0
    );
    const incorrectCount = Number(
        summary.incorrect_count ?? rawData.resumen_general?.total_incorrectas ?? 0
    );
    const omittedCount = Number(
        summary.omitted_count ?? rawData.resumen_general?.total_omitidas ?? 0
    );
    const bestAccuracy = Number(
        summary.best_accuracy ?? rawData.resumen_general?.mejor_resultado ?? accuracy
    );
    const bestExamScore = Number(
        summary.best_exam_score ?? rawData.resumen_general?.mejor_puntaje_simulacro ?? 0
    );
    const streakDays = Number(summary.streak_days ?? rawData.resumen_general?.racha_dias ?? 0);

    return {
        ...rawData,
        summary: {
            total_answered: totalAnswered,
            correct_count: correctCount,
            incorrect_count: incorrectCount,
            omitted_count: omittedCount,
            accuracy,
            best_accuracy: bestAccuracy,
            best_exam_score: bestExamScore,
            completed_exams_count: completedExamsCount,
            practice_questions_answered: practiceQuestionsAnswered,
            practice_sessions_count: practiceSessionsCount,
            total_time_seconds: Number(summary.total_time_seconds || 0),
            streak_days: streakDays,
        },
        by_subject: bySubject,
        by_topic: byTopic,
        weak_topics: weakTopics,
        strong_topics: strongTopics,
        daily_activity: dailyActivity,
        academic_analysis: academicAnalysis,
        recent_exams: recentExams,
        recent_activity: recentActivity,
        resumen_general: {
            porcentaje_global_aciertos: accuracy,
            mejor_resultado: bestAccuracy,
            mejor_puntaje_simulacro: bestExamScore,
            total_simulacros_completados: completedExamsCount,
            total_practicas_realizadas: practiceSessionsCount,
            total_preguntas_practica: practiceQuestionsAnswered,
            total_preguntas_respondidas: totalAnswered,
            total_correctas: correctCount,
            total_incorrectas: incorrectCount,
            total_omitidas: omittedCount,
            racha_dias: streakDays,
        },
        temas_fuertes: strongTopics,
        temas_debiles: weakTopics,
        ultimos_simulacros: recentExams,
    };
};

export const useProgressStore = create((set) => ({
    dashboard: null,
    loading: false,
    error: null,

    // Acción asíncrona: consulta las métricas reales de progreso académico desde Question Bank
    fetchDashboardProgress: async (period = "all") => {
        set({ loading: true, error: null });
        try {
            const response = await getProgressDashboard(period);
            if (response && response.success) {
                const normalized = normalizeProgressPayload(response.data);
                set({
                    dashboard: normalized,
                    loading: false,
                    error: null,
                });
                return normalized;
            } else {
                const errorMsg =
                    response?.message || "No fue posible obtener el progreso académico del estudiante.";
                set({
                    loading: false,
                    error: errorMsg,
                });
                return null;
            }
        } catch (err) {
            const errorMsg =
                err?.response?.data?.message ||
                err?.message ||
                "Error al conectar con el servidor académico de progreso.";
            set({
                loading: false,
                error: errorMsg,
            });
            return null;
        }
    },

    // Permite limpiar o reiniciar el estado de progreso
    resetProgress: () =>
        set({
            dashboard: null,
            loading: false,
            error: null,
        }),
}));

export default useProgressStore;
