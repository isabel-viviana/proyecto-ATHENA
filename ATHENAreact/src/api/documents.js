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

