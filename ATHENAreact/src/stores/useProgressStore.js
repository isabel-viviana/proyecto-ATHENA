import { create } from "zustand";
import { getProgressDashboard } from "../api/progress.js";

export const useProgressStore = create((set) => ({
    dashboard: null,
    loading: false,
    error: null,

    // Acción asíncrona: consulta las métricas de progreso académico desde la API de Laravel
    fetchDashboardProgress: async () => {
        set({ loading: true, error: null });
        try {
            const response = await getProgressDashboard();
            if (response && response.success) {
                set({
                    dashboard: response.data,
                    loading: false,
                    error: null,
                });
                return response.data;
            } else {
                const errorMsg =
                    response?.message || "No fue posible obtener el progreso del estudiante.";
                set({
                    loading: false,
                    error: errorMsg,
                });
                return null;
            }
        } catch (err) {
            const errorMsg =
                err?.response?.data?.message ||
                err?.message ||
                "Error al conectar con el servidor de progreso.";
            set({
                loading: false,
                error: errorMsg,
            });
            return null;
        }
    },

    // Permite limpiar o reiniciar el estado de progreso
    resetProgress: () =>
        set({
            dashboard: null,
            loading: false,
            error: null,
        }),
}));

export default useProgressStore;
