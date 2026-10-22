import api from './axios.js';

export const getProgressDashboard = async () => {
    const response = await api.get('/progress');
    return response.data;
};

export const getProgressStats = async () => {
    const response = await api.get('/progress/stats');
    return response.data;
};

export const getProgressHistory = async () => {
    const response = await api.get('/progress/history');
    return response.data;
};

export const getSubjectPerformance = async (subjectId) => {
    const response = await api.get(`/progress/subject/${subjectId}`);
    return response.data;
};

export const getTopicPerformance = async (topicId) => {
    const response = await api.get(`/progress/topic/${topicId}`);
    return response.data;
}; 