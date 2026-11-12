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
 * Soporta filtro de período: '7d', '30d', 'all'.
 */
export const getStudentProgress = async (period = 'all') => {
    const res = await questionBankApi.get('/student/progress', { params: { period } });
    return res.data;
};

/**
 * Consulta el perfil institucional completo del estudiante (datos personales, académicos y gamificación).
 */
export const getStudentProfileData = async () => {
    const res = await questionBankApi.get('/student/profile');
    return res.data;
};

/**
 * Actualiza los campos permitidos del perfil del estudiante.
 */
export const updateStudentProfileData = async (data) => {
    const res = await questionBankApi.put('/student/profile', data);
    return res.data;
};

/**
 * Cambia la contraseña institucional verificando la actual.
 */
export const changeStudentPassword = async (data) => {
    const res = await questionBankApi.post('/student/profile/password', data);
    return res.data;
};

/**
 * Consulta el ranking institucional oficial de estudiantes con Top 3 y posición del usuario.
 */
export const getStudentRanking = async () => {
    const res = await questionBankApi.get('/student/ranking');
    return res.data;
};

/**
 * Consulta la posición específica del estudiante autenticado en el ranking.
 */
export const getMyRanking = async () => {
    const res = await questionBankApi.get('/student/ranking/me');
    return res.data;
};

/**
 * Consulta las notificaciones institucionales del estudiante.
 * Filtro opcional: 'all', 'unread', 'read'.
 */
export const getStudentNotifications = async (filter = 'all') => {
    const res = await questionBankApi.get('/student/notifications', { params: { filter } });
    return res.data;
};

/**
 * Obtiene el número de notificaciones no leídas para el badge del menú.
 */
export const getUnreadNotificationsCount = async () => {
    const res = await questionBankApi.get('/student/notifications/unread-count');
    return res.data;
};

/**
 * Marca una notificación específica como leída.
 */
export const markNotificationAsRead = async (id) => {
    const res = await questionBankApi.patch(`/student/notifications/${id}/read`);
    return res.data;
};

/**
 * Marca todas las notificaciones pendientes como leídas.
 */
export const markAllNotificationsAsRead = async () => {
    const res = await questionBankApi.patch('/student/notifications/read-all');
    return res.data;
};

/**
 * Elimina una notificación de la bandeja.
 */
export const deleteStudentNotification = async (id) => {
    const res = await questionBankApi.delete(`/student/notifications/${id}`);
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

