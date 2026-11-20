import { useEffect } from "react";
import { Navigate, useLocation } from "react-router-dom";
import useAuthStore, { getRedirectPathByRole } from "../stores/useAuthStore";

const DEFAULT_STUDENT_ROLES = ["student", "user"];

/**
 * Protege rutas que requieren autenticación y valida el rol permitido (allowedRoles).
 * Redirige a /login si no está autenticado, o al dashboard correspondiente a su rol
 * si intenta acceder a una ruta de otro rol.
 */
export default function ProtectedRoute({
    children,
    allowedRoles = DEFAULT_STUDENT_ROLES,
}) {
    const isAuthenticated = useAuthStore((s) => s.isAuthenticated);
    const isInitialized = useAuthStore((s) => s.isInitialized);
    const isAuthLoading = useAuthStore((s) => s.isAuthLoading);
    const role = useAuthStore((s) => s.role);
    const fetchCurrentUser = useAuthStore((s) => s.fetchCurrentUser);
    const location = useLocation();

    // Validar sesión activa contra el backend (/api/auth/me) al cambiar de ruta protegida
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
                    Verificando sesión...
                </p>
            </div>
        );
    }

    if (!isAuthenticated) {
        return <Navigate to="/login" state={{ from: location }} replace />;
    }

    const currentRole = String(role || "student").toLowerCase();
    if (
        Array.isArray(allowedRoles) &&
        allowedRoles.length > 0 &&
        !allowedRoles.includes(currentRole)
    ) {
        return <Navigate to={getRedirectPathByRole(currentRole)} replace />;
    }

    return children;
}
