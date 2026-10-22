import { create } from "zustand";
import api from "../api/axios";

const errorMessage = (error) => error.response?.data?.message || "No fue posible completar la operación.";

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
            const user = response.data?.user ?? response.data?.data?.user ?? null;
            set({ user, isAuthenticated: true, loading: false });
            return response.data;
        } catch (error) {
            set({ loading: false, isAuthenticated: false, error: errorMessage(error) });
            throw error;
        }
    },
    fetchCurrentUser: async () => {
        set({ loading: true, error: null });
        try {
            const response = await api.get("/auth/me");
            const user = response.data?.user ?? response.data?.data?.user ?? response.data;
            set({ user, isAuthenticated: Boolean(user), loading: false });
            return user;
        } catch {
            set({ user: null, isAuthenticated: false, loading: false });
            return null;
        }
    },
    logout: async () => {
        set({ loading: true, error: null });
        try {
            await api.post("/auth/logout");
            set({ user: null, isAuthenticated: false, loading: false });
        } catch (error) {
            set({ loading: false, error: errorMessage(error) });
            throw error;
        }
    },
}));

export default useAuthStore;
