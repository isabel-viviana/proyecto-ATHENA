import { Navigate, useLocation } from "react-router-dom";
import useAuthStore from "../stores/useAuthStore";

const ADMIN_ROLES = ["superadmin", "super_admin", "admin", "support", "docente", "coordinador"];

/**
 * Protege rutas del panel administrativo.
 * Redirige a /admin/login si no está autenticado,
 * a /dashboard si es estudiante, y restringe /dashboard/superadmin exclusivamente a superadmin.
 */
export default function AdminRoute({ children }) {
    const { isAuthenticated, role, isInitialized } = useAuthStore();
    const location = useLocation();

    const storedAdminToken = localStorage.getItem("athena_admin_token");
    let storedAdminRole = null;
    try {
        const parsed = JSON.parse(localStorage.getItem("athena_admin_user") || "null");
        storedAdminRole = parsed?.role || null;
    } catch {
        storedAdminRole = null;
    }

    const effectiveRole = String(role || storedAdminRole || "").toLowerCase().trim();
    const normalizedRole = effectiveRole === "super_admin" ? "superadmin" : effectiveRole;
    const hasAdminSession = (isAuthenticated || Boolean(storedAdminToken)) && ADMIN_ROLES.includes(effectiveRole);

    if (!isInitialized && !storedAdminToken) {
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

    if (!isAuthenticated && !storedAdminToken) {
        return <Navigate to="/admin/login" state={{ from: location }} replace />;
    }

    if (!hasAdminSession) {
        return <Navigate to="/dashboard" replace />;
    }

    if (
        (location.pathname.startsWith("/dashboard/superadmin") || location.pathname.startsWith("/super")) &&
        normalizedRole !== "superadmin"
    ) {
        return <Navigate to={normalizedRole === "support" ? "/support/dashboard" : "/dashboard/admin"} replace />;
    }

    return children;
}
