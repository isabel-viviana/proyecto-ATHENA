import axios from "axios";

const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api",
    withCredentials: true,
    headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
    },
});

/**
 * Hidratar cabecera Authorization inicial si ya existe un token en localStorage.
 */
const initialToken = localStorage.getItem("athena_token");
if (initialToken) {
    api.defaults.headers.common["Authorization"] = `Bearer ${initialToken}`;
}

/**
 * Función utilitaria para sincronizar el token en localStorage y en los defaults de Axios.
 */
export const setAuthToken = (token) => {
    if (token) {
        localStorage.setItem("athena_token", token);
        api.defaults.headers.common["Authorization"] = `Bearer ${token}`;
    } else {
        localStorage.removeItem("athena_token");
        delete api.defaults.headers.common["Authorization"];
    }
};

/**
 * Interceptor de request: asegura que cada petición lleve el header Authorization: Bearer <token>.
 */
api.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem("athena_token");

        if (token) {
            config.headers = config.headers || {};
            config.headers.Authorization = `Bearer ${token}`;
        }

        return config;
    },
    (error) => Promise.reject(error)
);

/**
 * Interceptor de response: maneja errores 401 globalmente.
 * Si una petición a una ruta protegida recibe 401, limpia el token inválido.
 */
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            const url = error.config?.url || "";
            // No limpiar token en endpoints de login/registro para no alterar el flujo de captura de errores
            if (
                !url.includes("/auth/firebase-login") &&
                !url.includes("/auth/login") &&
                !url.includes("/auth/register")
            ) {
                setAuthToken(null);
            }
        }
        return Promise.reject(error);
    }
);

export default api;
