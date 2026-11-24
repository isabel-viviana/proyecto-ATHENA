import { useState, useEffect } from "react";
import { useNavigate, Link } from "react-router-dom";
import "./login_admin.css";
import ZorritoLogo from "../../assets/images/Zorrito6.png";
import {
    useAuthStore,
    getRedirectPathByRole,
    normalizeUserRole,
} from "../../stores/useAuthStore";
import { adminLogin } from "../../api/documents";
import { setAuthToken } from "../../api/axios";

const LoginAdmin = () => {
    const [email, setEmail] = useState("");
    const [password, setPassword] = useState("");
    const [error, setError] = useState("");
    const [isSubmitting, setIsSubmitting] = useState(false);

    const login = useAuthStore((state) => state.login);
    const logout = useAuthStore((state) => state.logout);
    const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
    const role = useAuthStore((state) => state.role);

    const navigate = useNavigate();

    // Redirección si ya está autenticado con rol administrativo
    useEffect(() => {
        const normalized = normalizeUserRole(role);
        if (isAuthenticated && ["superadmin", "admin"].includes(normalized)) {
            navigate(getRedirectPathByRole(normalized), { replace: true });
        }
    }, [isAuthenticated, role, navigate]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError("");

        if (!email.trim() || !password.trim()) {
            setError("Por favor ingresa tu correo y contraseña institucional.");
            return;
        }

        setIsSubmitting(true);

        try {
            let userObj = null;
            let tokenStr = null;

            try {
                const qbRes = await adminLogin(email.trim(), password);
                if (qbRes?.success && (qbRes?.token || qbRes?.data?.token)) {
                    tokenStr = qbRes.token || qbRes.data?.token;
                    userObj = qbRes.admin || qbRes.data?.user;
                }
            } catch (qbErr) {
                if (qbErr?.response?.status === 403) {
                    throw new Error(qbErr.response.data?.message || "No tienes permisos administrativos.");
                }
                const data = await login({ email: email.trim(), password });
                userObj = data?.user;
                tokenStr = data?.token || data?.access_token;
            }

            const rawRole = String(userObj?.role || "").toLowerCase().trim();
            if (!["superadmin", "super_admin", "admin", "support", "docente", "coordinador"].includes(rawRole)) {
                setError("Esta cuenta no tiene privilegios administrativos.");
                await logout();
                setIsSubmitting(false);
                return;
            }
            const userRole = normalizeUserRole(rawRole);

            if (tokenStr && userObj) {
                localStorage.setItem("athena_admin_token", tokenStr);
                localStorage.setItem("athena_admin_user", JSON.stringify(userObj));
                setAuthToken(tokenStr);
                useAuthStore.setState({
                    user: userObj,
                    token: tokenStr,
                    role: userRole,
                    isAuthenticated: true,
                    isInitialized: true,
                    loading: false,
                    error: null,
                });
            }

            navigate(getRedirectPathByRole(userRole));
        } catch (err) {
            setIsSubmitting(false);
            const msg =
                err?.response?.data?.message ||
                err?.message ||
                "Credenciales de administrador inválidas.";
            setError(msg);
        }
    };

    return (
        <div className="admin-login-wrapper">
            {/* Elementos decorativos de fondo con brillo verde ATHENA */}
            <div className="admin-login-glow-1"></div>
            <div className="admin-login-glow-2"></div>

            <div className="admin-login-card">
                {/* Cabecera con Logo y Título */}
                <div className="admin-login-header">
                    <div className="admin-login-logo-container">
                        <img
                            src={ZorritoLogo}
                            alt="Logo ATHENA"
                            className="admin-login-logo"
                        />
                    </div>
                    <div>
                        <span className="admin-login-badge">
                            <span className="admin-login-badge-dot"></span>
                            ATHENA Core
                        </span>
                    </div>
                    <h1 className="admin-login-title">Panel Administrativo</h1>
                    <p className="admin-login-subtitle">
                        Gestión académica y control del sistema
                    </p>
                </div>

                {/* Formulario de Login */}
                <form onSubmit={handleSubmit} className="admin-login-form">
                    <div className="admin-form-group">
                        <label className="admin-form-label" htmlFor="admin-email">
                            <i className="fa-solid fa-envelope"></i> Correo Electrónico
                        </label>
                        <div className="admin-input-box">
                            <i className="fa-solid fa-at admin-input-icon"></i>
                            <input
                                id="admin-email"
                                type="email"
                                placeholder="admin@athena.edu.co"
                                className="admin-input"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                autoFocus
                                required
                            />
                        </div>
                    </div>

                    <div className="admin-form-group">
                        <label className="admin-form-label" htmlFor="admin-password">
                            <i className="fa-solid fa-lock"></i> Contraseña
                        </label>
                        <div className="admin-input-box">
                            <i className="fa-solid fa-key admin-input-icon"></i>
                            <input
                                id="admin-password"
                                type="password"
                                placeholder="••••••••••••"
                                className="admin-input"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                required
                            />
                        </div>
                    </div>

                    {error && (
                        <div className="admin-login-error">
                            <i className="fa-solid fa-triangle-exclamation"></i>
                            <span>{error}</span>
                        </div>
                    )}

                    <button
                        type="submit"
                        className="admin-btn-submit"
                        disabled={isSubmitting}
                    >
                        {isSubmitting ? (
                            <>
                                <i className="fa-solid fa-circle-notch fa-spin"></i>
                                <span>Accediendo...</span>
                            </>
                        ) : (
                            <>
                                <span>Ingresar</span>
                                <i className="fa-solid fa-arrow-right-to-bracket"></i>
                            </>
                        )}
                    </button>
                </form>

                {/* Enlaces y Pie de página */}
                <div className="admin-login-footer">
                    <Link to="/login" className="admin-student-portal-link">
                        <i className="fa-solid fa-arrow-left"></i>
                        <span>Ir al portal de estudiantes</span>
                    </Link>
                    <p className="admin-login-disclaimer">
                        Acceso restringido a personal autorizado y docentes de ATHENA.
                    </p>
                </div>
            </div>
        </div>
    );
};

export default LoginAdmin;
