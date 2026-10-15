import { useState } from "react";
import { useNavigate, Link } from "react-router-dom";
import "./login_admin.css";
import ZorritoLogo from "../../assets/images/Zorrito6.png";

const LoginAdmin = () => {
    const [email, setEmail] = useState("");
    const [password, setPassword] = useState("");
    const [error, setError] = useState("");
    const [isSubmitting, setIsSubmitting] = useState(false);

    const navigate = useNavigate();

    const handleSubmit = (e) => {
        e.preventDefault();
        setError("");

        if (!email.trim() || !password.trim()) {
            setError("Por favor ingresa tu correo y contraseña institucional.");
            return;
        }

        setIsSubmitting(true);

        // Simulación de ingreso y redirección requerida a /super/dashboard
        setTimeout(() => {
            setIsSubmitting(false);
            navigate("/super/dashboard");
        }, 500);
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
