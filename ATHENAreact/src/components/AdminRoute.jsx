import { useEffect } from "react";
import { Navigate, useLocation } from "react-router-dom";
import useAuthStore, { getRedirectPathByRole } from "../stores/useAuthStore";

const ADMIN_ROLES = ["superadmin", "super_admin", "admin", "support"];

/**
 * Protege rutas del panel administrativo.
 * Redirige a /admin/login si no está autenticado,
 * o inmediatamente al dashboard de estudiante (/dashboard) si un estudiante
 * intenta escribir una ruta de administrador en la barra de direcciones.
 */
export default function AdminRoute({ children, allowedRoles = ADMIN_ROLES }) {
    const isAuthenticated = useAuthStore((s) => s.isAuthenticated);
    const role = useAuthStore((s) => s.role);
    const isInitialized = useAuthStore((s) => s.isInitialized);
    const isAuthLoading = useAuthStore((s) => s.isAuthLoading);
    const fetchCurrentUser = useAuthStore((s) => s.fetchCurrentUser);
    const location = useLocation();

    // Validar sesión y rol real contra el backend (/api/auth/me) al navegar por rutas de admin
    useEffect(() => {
        if (isInitialized && isAuthenticated) {
            fetchCurrentUser();
        }
    }, [location.pathname, isInitialized, isAuthenticated, fetchCurrentUser]);

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
                    Verificando permisos...
                </p>
            </div>
        );
    }

    if (!isAuthenticated) {
        return <Navigate to="/admin/login" state={{ from: location }} replace />;
    }

    const currentRole = String(role || "student").toLowerCase();
    if (!allowedRoles.includes(currentRole)) {
        return <Navigate to={getRedirectPathByRole(currentRole)} replace />;
    }

    return children;
}
