import { create } from "zustand";
import api from "../api/axios";

const errorMessage = (error) => {
    if (!error.response) {
        if (error.message && error.message !== "Network Error") {
            return error.message;
        }

        return "No se pudo conectar con el servidor. Verifica que Laravel esté iniciado e inténtalo de nuevo.";
    }

    const validationError = Object.values(error.response.data?.errors ?? {})
        .flat()
        .find(Boolean);

    return validationError
        ?? error.response.data?.message
        ?? "No fue posible completar la operación.";
};

export const useAuthStore = create((set) => ({
    user: null,
    isAuthenticated: false,
    loading: false,
    error: null,
    clearError: () => set({ error: null }),
    setError: (error) => set({ error }),
    login: async (credentials) => {
        set({ loading: true, error: null });
        try {
            const response = await api.post("/auth/login", credentials);
            const { user, token } = response.data;

            if (!user || !token) {
                throw new Error("El servidor no devolvió la sesión de usuario.");
            }

            localStorage.setItem("athena_token", token);
            set({ user, isAuthenticated: true, loading: false });
            return response.data;
        } catch (error) {
            localStorage.removeItem("athena_token");
            set({ loading: false, isAuthenticated: false, error: errorMessage(error) });
            throw error;
        }
    },
    register: async (userData) => {
        set({ loading: true, error: null });
        try {
            const response = await api.post("/auth/register", userData);
            const { user, token } = response.data;

            if (!user || !token) {
                throw new Error("El servidor no devolvió la sesión de usuario.");
            }

            localStorage.setItem("athena_token", token);
            set({ user, isAuthenticated: true, loading: false });
            return response.data;
        } catch (error) {
            set({ loading: false, isAuthenticated: false, error: errorMessage(error) });
            throw error;
        }
    },
    fetchCurrentUser: async () => {
        if (!localStorage.getItem("athena_token")) {
            set({ user: null, isAuthenticated: false, loading: false });
            return null;
        }

        set({ loading: true, error: null });
        try {
            const response = await api.get("/auth/me");
            const user = response.data?.user;
            set({ user, isAuthenticated: Boolean(user), loading: false });
            return user;
        } catch (error) {
            if (error.response?.status === 401) {
                localStorage.removeItem("athena_token");
                set({ user: null, isAuthenticated: false, loading: false });
                return null;
            }

            set({ loading: false, error: errorMessage(error) });
            return null;
        }
    },
    logout: async () => {
        set({ loading: true, error: null });
        try {
            await api.post("/auth/logout");
            localStorage.removeItem("athena_token");
            set({ user: null, isAuthenticated: false, loading: false });
        } catch (error) {
            set({ loading: false, error: errorMessage(error) });
            throw error;
        }
    },
}));

export default useAuthStore;
