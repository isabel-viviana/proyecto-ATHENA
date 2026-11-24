import questionBankApi from './questionBankApi.js';

/**
 * Consulta las métricas académicas reales del estudiante desde ATHENA Question Bank (puerto 8001).
 * Reutiliza el endpoint oficial /student/progress conectado a Supabase PostgreSQL.
 */
export const getProgressDashboard = async (period = 'all') => {
    const response = await questionBankApi.get('/student/progress', {
        params: { period },
    });
    return response.data;
};

export const getProgressStats = async (period = 'all') => {
    const response = await questionBankApi.get('/student/progress', {
        params: { period },
    });
    return response.data;
};

export const getProgressHistory = async () => {
    const response = await questionBankApi.get('/student/exams/history');
    return response.data;
};

export const getSubjectPerformance = async (subjectId, period = 'all') => {
    const response = await questionBankApi.get('/student/progress', {
        params: { period },
    });
    const bySubject = response.data?.data?.by_subject || [];
    return {
        success: Boolean(response.data?.success),
        data: bySubject.find((s) => Number(s.subject_id) === Number(subjectId)) || null,
    };
};

export const getTopicPerformance = async (topicId, period = 'all') => {
    const response = await questionBankApi.get('/student/progress', {
        params: { period },
    });
    const byTopic = response.data?.data?.by_topic || [];
    return {
        success: Boolean(response.data?.success),
        data: byTopic.find((t) => Number(t.topic_id) === Number(topicId)) || null,
    };
};
