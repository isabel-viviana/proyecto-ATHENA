import questionBankApi from './questionBankApi';

/**
 * Consulta las opciones de configuración disponibles para simulacros.
 */
export const getExamConfigOptions = async () => {
    const res = await questionBankApi.get('/student/exams/config-options');
    return res.data;
};

/**
 * Inicia un nuevo simulacro estructurado con preguntas reales seleccionadas en backend.
 */
export const startExam = async (config) => {
    const res = await questionBankApi.post('/student/exams/start', config);
    return res.data;
};

/**
 * Consulta si hay un simulacro en progreso activo para recuperar la sesión tras recargar.
 */
export const getActiveExam = async () => {
    const res = await questionBankApi.get('/student/exams/active');
    return res.data;
};

/**
 * Consulta los datos y preguntas de un intento de simulacro.
 */
export const getExamAttempt = async (attemptId) => {
    const res = await questionBankApi.get(`/student/exams/${attemptId}`);
    return res.data;
};

/**
 * Guarda o actualiza una respuesta en el simulacro activo.
 */
export const saveExamAnswer = async (attemptId, data) => {
    const res = await questionBankApi.post(`/student/exams/${attemptId}/answers`, data);
    return res.data;
};

/**
 * Finaliza y califica el simulacro en el backend oficial.
 */
export const finishExamAttempt = async (attemptId) => {
    const res = await questionBankApi.post(`/student/exams/${attemptId}/finish`);
    return res.data;
};

/**
 * Consulta los resultados detallados y revisión pregunta por pregunta.
 */
export const getExamResults = async (attemptId) => {
    const res = await questionBankApi.get(`/student/exams/${attemptId}/results`);
    return res.data;
};

/**
 * Consulta el historial de simulacros finalizados del estudiante.
 */
export const getExamHistory = async () => {
    const res = await questionBankApi.get('/student/exams/history');
    return res.data;
};

// Aliases retrocompatibles para integración fluida
export const configureExam = startExam;
export const getExamQuestions = getExamAttempt;
export const submitAnswer = saveExamAnswer;
export const finishExam = finishExamAttempt;
