import { Navigate } from "react-router-dom";
import useAuthStore, { getRedirectPathByRole } from "../stores/useAuthStore";

/**
 * Envuelve rutas públicas de autenticación (/login, /register, /admin/login).
 * Si el usuario ya tiene una sesión válida activa, lo redirige automáticamente
 * a su dashboard correspondiente según su rol.
 */
export default function GuestRoute({ children }) {
    const isAuthenticated = useAuthStore((s) => s.isAuthenticated);
    const isInitialized = useAuthStore((s) => s.isInitialized);
    const isAuthLoading = useAuthStore((s) => s.isAuthLoading);
    const role = useAuthStore((s) => s.role);

    if (!isInitialized || isAuthLoading) {
        return (
            <div
                style={{
                    display: "flex",
                    flexDirection: "column",
                    alignItems: "center",
                    justifyContent: "center",
                    minHeight: "100vh",
                    backgroundColor: "#080c16",
                    color: "#6366f1",
                }}
            >
                <div style={{ fontSize: "38px", marginBottom: "16px" }}>
                    <i className="fas fa-circle-notch fa-spin"></i>
                </div>
                <p style={{ color: "#94a3b8", fontSize: "14px" }}>
                    Verificando sesión...
                </p>
            </div>
        );
    }

    if (isAuthenticated && role) {
        return <Navigate to={getRedirectPathByRole(role)} replace />;
    }

    return children;
}

