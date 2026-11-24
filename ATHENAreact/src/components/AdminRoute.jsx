import { useEffect } from "react";
import { Navigate, useLocation } from "react-router-dom";
import useAuthStore, {
    getRedirectPathByRole,
    normalizeUserRole,
} from "../stores/useAuthStore";

const ADMIN_ROLES = ["superadmin", "admin"];

/**
 * Protege rutas del panel administrativo.
 * Maneja únicamente los roles administrativos activos ('superadmin' y 'admin').
 * - Si no está autenticado -> redirige a /login.
 * - Si es 'student' -> redirige a /dashboard.
 * - Si es 'admin' e intenta acceder a rutas exclusivas de 'superadmin' (/dashboard/superadmin, /super/*, /admin/users) -> redirige a /dashboard/admin.
 * - Si es 'superadmin' y entra a /dashboard/admin -> redirige a su panel /dashboard/superadmin.
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

    const storedAdminToken = localStorage.getItem("athena_admin_token");
    let storedAdminRole = null;
    try {
        const parsed = JSON.parse(
            localStorage.getItem("athena_admin_user") || "null"
        );
        storedAdminRole = parsed?.role || null;
    } catch {
        storedAdminRole = null;
    }

    const effectiveRawRole = role || storedAdminRole || "";
    const normalizedRole = effectiveRawRole
        ? normalizeUserRole(effectiveRawRole)
        : "";
    const normalizedAllowed = allowedRoles.map((r) => normalizeUserRole(r));

    const hasAdminSession =
        (isAuthenticated || Boolean(storedAdminToken)) &&
        Boolean(normalizedRole) &&
        normalizedAllowed.includes(normalizedRole);

    if ((!isInitialized || isAuthLoading) && !storedAdminToken) {
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
        return <Navigate to="/login" state={{ from: location }} replace />;
    }

    if (!hasAdminSession) {
        return (
            <Navigate
                to={getRedirectPathByRole(normalizedRole || "student")}
                replace
            />
        );
    }

    // Restringir rutas exclusivas de SuperAdmin (/dashboard/superadmin, /super/*, /admin/users)
    const isSuperOnlyPath =
        location.pathname.startsWith("/dashboard/superadmin") ||
        location.pathname.startsWith("/super") ||
        location.pathname.startsWith("/admin/users");

    if (isSuperOnlyPath && normalizedRole !== "superadmin") {
        return <Navigate to="/dashboard/admin" replace />;
    }

    // Asegurar que superadmin siempre entre a su propio dashboard principal (/dashboard/superadmin)
    if (
        (location.pathname === "/dashboard/admin" ||
            location.pathname === "/admin/dashboard") &&
        normalizedRole === "superadmin"
    ) {
        return <Navigate to="/dashboard/superadmin" replace />;
    }

    return children;
}
