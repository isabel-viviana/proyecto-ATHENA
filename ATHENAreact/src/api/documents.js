import questionBankApi from './questionBankApi';

/**
 * Obtener listado de documentos con filtros y paginación.
 * @param {Object} params - { status, year, exam_type, search, per_page, page }
 */
export const getDocuments = async (params = {}) => {
    const response = await questionBankApi.get('/admin/documents', { params });
    return response.data;
};

/**
 * Subir un nuevo documento PDF a Supabase Storage y registrar en PostgreSQL.
 * @param {FormData} formData - Debe contener 'file', 'title', 'year', 'exam_type', 'has_answer_key'
 * @param {Function} [onUploadProgress] - Callback opcional para barra de progreso
 */
export const uploadDocument = async (formData, onUploadProgress = null) => {
    const response = await questionBankApi.post('/admin/documents', formData, {
        headers: {
            'Content-Type': 'multipart/form-data',
        },
        onUploadProgress,
    });
    return response.data;
};

/**
 * Consultar detalles de un documento por su ID.
 * @param {string} id - UUID del documento
 * @param {boolean} [signedUrl=false] - Si es true, solicita URL temporal firmada para visualización
 */
export const getDocument = async (id, signedUrl = false) => {
    const response = await questionBankApi.get(`/admin/documents/${id}`, {
        params: { signed_url: signedUrl ? 'true' : 'false' },
    });
    return response.data;
};

/**
 * Eliminar un documento con confirmación explícita.
 * @param {string} id - UUID del documento
 */
export const deleteDocument = async (id) => {
    const response = await questionBankApi.delete(`/admin/documents/${id}`, {
        params: { confirm: 'true' },
    });
    return response.data;
};

/**
 * Disparar el proceso de extracción de preguntas con Gemini para un documento.
 * @param {string} documentId - UUID del documento
 */
export const extractDocumentQuestions = async (documentId) => {
    const response = await questionBankApi.post(`/admin/documents/${documentId}/extract`);
    return response.data;
};

/**
 * Consultar el estado de procesamiento y metadatos de extracción del documento.
 * @param {string} documentId - UUID del documento
 */
export const getExtractionStatus = async (documentId) => {
    const response = await questionBankApi.get(`/admin/documents/${documentId}/extraction-status`);
    return response.data;
};

/**
 * Consultar las preguntas, opciones y estímulos extraídos vinculados a un documento.
 * @param {string} documentId - UUID del documento
 */
export const getExtractedQuestions = async (documentId) => {
    const response = await questionBankApi.get(`/admin/documents/${documentId}/extracted-questions`);
    return response.data;
};

/**
 * Consultar listado paginado de preguntas reales del banco con filtros.
 * @param {Object} params - { status, subject_id, search, per_page, page }
 */
export const getQuestions = async (params = {}) => {
    const response = await questionBankApi.get('/admin/questions', { params });
    return response.data;
};

/**
 * Consultar detalle completo de una pregunta por su ID.
 * @param {string} questionId - UUID de la pregunta
 */
export const getQuestion = async (questionId) => {
    const response = await questionBankApi.get(`/admin/questions/${questionId}`);
    return response.data;
};

/**
 * Actualizar enunciado, opciones, materia, tema, clave y justificación de una pregunta.
 * @param {string} questionId - UUID de la pregunta
 * @param {Object} payload - Datos de actualización
 */
export const updateQuestion = async (questionId, payload) => {
    const response = await questionBankApi.put(`/admin/questions/${questionId}`, payload);
    return response.data;
};

/**
 * Aprobar pedagógicamente una pregunta validada para el banco oficial.
 * @param {string} questionId - UUID de la pregunta
 */
export const approveQuestion = async (questionId) => {
    const response = await questionBankApi.post(`/admin/questions/${questionId}/approve`);
    return response.data;
};

/**
 * Rechazar y archivar un reactivo extraído sin alterar estímulos asociados.
 * @param {string} questionId - UUID de la pregunta
 */
export const rejectQuestion = async (questionId) => {
    const response = await questionBankApi.post(`/admin/questions/${questionId}/reject`);
    return response.data;
};

/**
 * Consultar listado de áreas académicas oficiales ICFES.
 */
export const getSubjectsCatalog = async () => {
    const response = await questionBankApi.get('/admin/catalogs/subjects');
    return response.data;
};

/**
 * Consultar listado de temas curriculares por materia.
 * @param {number} [subjectId] - ID de la materia
 */
export const getTopicsCatalog = async (subjectId = null) => {
    const params = subjectId ? { subject_id: subjectId } : {};
    const response = await questionBankApi.get('/admin/catalogs/topics', { params });
    return response.data;
};

/**
 * URL del recurso visual servido por el backend desde el bucket privado icfes-media.
 * No contiene credenciales: el microservicio lee el archivo y lo transmite.
 * @param {string} path - Ruta relativa dentro de icfes-media
 */
export const getMediaUrl = (path) => {
    if (!path) return null;
    const base = (questionBankApi.defaults.baseURL || '').replace(/\/$/, '');
    return `${base}/admin/media?path=${encodeURIComponent(path)}`;
};

/**
 * Recursos visuales de un documento registrados en media_assets (incluye pendientes, confirmados y destinos disponibles).
 * @param {string} documentId - UUID del documento
 */
export const getDocumentMedia = async (documentId) => {
    const response = await questionBankApi.get(`/admin/documents/${documentId}/media`);
    return response.data;
};

/**
 * Listar recursos visuales registrados en media_assets con filtros opcionales.
 * @param {Object} [params] - { source_document_id, association_status }
 */
export const getMediaAssets = async (params = {}) => {
    const response = await questionBankApi.get('/admin/media-assets', { params });
    return response.data;
};

/**
 * Confirmar la asociación de un recurso visual a un estímulo o pregunta del mismo documento.
 * @param {string} assetId - UUID del media_asset
 * @param {Object} payload - { target_type: 'stimulus'|'question', target_id: string }
 */
export const associateMediaAsset = async (assetId, payload) => {
    const response = await questionBankApi.post(`/admin/media-assets/${assetId}/associate`, payload);
    return response.data;
};

/**
 * Desasociar de forma controlada un recurso visual, devolviéndolo a estado 'pending'.
 * @param {string} assetId - UUID del media_asset
 */
export const disassociateMediaAsset = async (assetId) => {
    const response = await questionBankApi.post(`/admin/media-assets/${assetId}/disassociate`);
    return response.data;
};

/**
 * Aprobar pedagógicamente de forma masiva un conjunto de preguntas seleccionadas.
 * @param {string[]} questionIds - Lista de UUIDs de las preguntas a aprobar
 */
export const bulkApproveQuestions = async (questionIds) => {
    const response = await questionBankApi.post('/admin/questions/bulk-approve', {
        question_ids: questionIds,
    });
    return response.data;
};





