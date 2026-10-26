import { Navigate, useLocation } from "react-router-dom";
import useAuthStore from "../stores/useAuthStore";

/**
 * Protege rutas que requieren autenticación.
 * Redirige a /login si el usuario no está autenticado.
 */
export default function ProtectedRoute({ children }) {
    const { isAuthenticated, isInitialized } = useAuthStore();
    const location = useLocation();

    // Mientras Firebase / Laravel verifican la sesión inicial al cargar la app:
    if (!isInitialized) {
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

    if (!isAuthenticated) {
        return <Navigate to="/login" state={{ from: location }} replace />;
    }

    return children;
}
