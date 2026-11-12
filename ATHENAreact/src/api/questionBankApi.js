import axios from 'axios';

/**
 * Cliente Axios independiente para el microservicio ATHENA Question Bank.
 * Se conecta a Laravel Question Bank (puerto local 8001).
 * Mantiene total aislamiento del cliente principal (ATHENA Core, puerto 8000).
 */
const questionBankApi = axios.create({
    baseURL: import.meta.env.VITE_QUESTION_BANK_API_URL || 'http://127.0.0.1:8001/api/v1',
    timeout: 180000,
    headers: {
        'Accept': 'application/json',
    },
});

// Interceptor para inyectar token JWT/Sanctum correspondiente según el tipo de ruta (/admin vs /student)
questionBankApi.interceptors.request.use((config) => {
    try {
        const url = String(config.url || '');
        const isAdminRoute = url.startsWith('/admin') || url.includes('/admin/');
        const token = isAdminRoute
            ? (localStorage.getItem('athena_admin_token') || localStorage.getItem('athena_token'))
            : localStorage.getItem('athena_token');

        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
    } catch {
        // En caso de SSR o acceso restringido a localStorage
    }
    return config;
}, (error) => {
    return Promise.reject(error);
});

// Interceptor de respuesta para proteger rutas administrativas si el token vence o no tiene privilegios
questionBankApi.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error?.response?.status;
        const url = String(error?.config?.url || '');
        const isAdminRoute = (url.startsWith('/admin') || url.includes('/admin/')) && !url.includes('/admin/auth/login');
        if (isAdminRoute && status === 401 && typeof window !== 'undefined') {
            localStorage.removeItem('athena_admin_token');
            localStorage.removeItem('athena_admin_user');
            if (!window.location.pathname.startsWith('/admin/login')) {
                window.location.href = '/admin/login';
            }
        }
        return Promise.reject(error);
    }
);

export default questionBankApi;

