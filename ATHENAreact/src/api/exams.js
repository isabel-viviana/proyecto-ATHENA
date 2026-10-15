import api from "./axios";

export const configureExam = async (examData) => {
    const response = await api.post("/exams/configure", examData);
    return response.data;
};

export const getExamQuestions = async (examId) => {
    const response = await api.get(`/exams/${examId}/questions`);
    return response.data;
};

export const submitAnswer = async (examId, answerData) => {
    const response = await api.post(`/exams/${examId}/answer`, answerData);
    return response.data;
};

export const finishExam = async (examId, data = {}) => {
    const response = await api.post(`/exams/${examId}/finish`, data);
    return response.data;
};

export const getExamResults = async (examId) => {
    const response = await api.get(`/exams/${examId}/results`);
    return response.data;
};