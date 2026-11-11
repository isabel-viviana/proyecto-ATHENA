import { Navigate, useLocation } from "react-router-dom";
import useAuthStore from "../stores/useAuthStore";

const ADMIN_ROLES = ["admin", "super_admin", "support"];

/**
 * Protege rutas del panel administrativo.
 * Redirige a /admin/login si no está autenticado,
 * o a /dashboard si no tiene rol de administrador.
 */
export default function AdminRoute({ children }) {
    const { isAuthenticated, role, isInitialized } = useAuthStore();
    const location = useLocation();

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
                    Verificando permisos...
                </p>
            </div>
        );
    }

    if (!isAuthenticated) {
        return <Navigate to="/admin/login" state={{ from: location }} replace />;
    }

    if (!ADMIN_ROLES.includes(role)) {
        return <Navigate to="/dashboard" replace />;
    }

    return children;
}
