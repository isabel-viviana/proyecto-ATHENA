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
 * Interceptor de response: maneja errores 401 y 403 (cuenta bloqueada) globalmente.
 */
api.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error.response?.status;
        const data = error.response?.data;
        const url = error.config?.url || "";

        // Si el usuario fue bloqueado mientras tenía sesión activa o al intentar autenticarse
        if (
            status === 403 &&
            (data?.code === "ACCOUNT_BLOCKED" ||
                String(data?.message || "")
                    .toLowerCase()
                    .includes("bloquead"))
        ) {
            const blockedMsg =
                data?.message ||
                "Tu cuenta ha sido bloqueada o suspendida por un administrador.";

            try {
                sessionStorage.setItem("athena_blocked_notice", blockedMsg);
                localStorage.removeItem("athena_admin_token");
                localStorage.removeItem("athena_admin_user");
            } catch {
                // Ignorar error de storage
            }

            setAuthToken(null);

            if (typeof window !== "undefined") {
                window.dispatchEvent(
                    new CustomEvent("athena:account-blocked", {
                        detail: { message: blockedMsg },
                    })
                );
            }
        } else if (status === 401) {
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
