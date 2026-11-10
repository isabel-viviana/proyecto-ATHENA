import questionBankApi from './questionBankApi';

/**
 * Consulta el catálogo de materias disponibles y el conteo de preguntas publicadas en cada una.
 */
export const getStudentSubjects = async () => {
    const res = await questionBankApi.get('/student/subjects');
    return res.data;
};

/**
 * Consulta preguntas publicadas para práctica de estudiantes con filtros, paginación y búsqueda.
 * El backend nunca expone 'is_correct' en este endpoint.
 */
export const getStudentQuestions = async (params = {}) => {
    const res = await questionBankApi.get('/student/questions', { params });
    return res.data;
};

/**
 * Consulta el detalle de una pregunta publicada para resolver.
 */
export const getStudentQuestion = async (id, studentId = null) => {
    const params = studentId ? { student_id: studentId } : {};
    const res = await questionBankApi.get(`/student/questions/${id}`, { params });
    return res.data;
};

/**
 * Envía la respuesta seleccionada por el estudiante para su validación oficial en Laravel.
 * El backend califica, persiste el resultado y devuelve la clave oficial y retroalimentación.
 */
export const submitStudentAnswer = async (questionId, data) => {
    const res = await questionBankApi.post(`/student/questions/${questionId}/answer`, data);
    return res.data;
};

/**
 * Autentica al estudiante con su correo y contraseña.
 * Retorna token JWT y perfil verificado.
 */
export const studentLogin = async (email, password) => {
    const res = await questionBankApi.post('/student/auth/login', { email, password });
    return res.data;
};

/**
 * Consulta el perfil del estudiante autenticado actualmente a partir de su Bearer token.
 */
export const getStudentProfile = async () => {
    const res = await questionBankApi.get('/student/auth/me');
    return res.data;
};

/**
 * Consulta el progreso, aciertos y métricas históricas del estudiante en tiempo real.
 */
export const getStudentProgress = async () => {
    const res = await questionBankApi.get('/student/progress');
    return res.data;
};

/**
 * Genera la URL absoluta para el streaming de imágenes desde el microservicio Question Bank.
 */
export const getStudentMediaUrl = (path) => {
    if (!path) return '';
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    const base = (questionBankApi.defaults.baseURL || '').replace(/\/$/, '');
    return `${base}/media?path=${encodeURIComponent(path)}`;
};

