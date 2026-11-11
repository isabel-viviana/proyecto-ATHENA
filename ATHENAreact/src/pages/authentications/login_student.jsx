import "./login_student.css";
import Zorrito2D from "../../assets/images/Zorrito2D.png";
import ZorritoLogo from "../../assets/images/Zorrito6.png";
import { Link, useNavigate } from "react-router-dom";
import { useState, useEffect } from "react";
import { useAuthStore, getRedirectPathByRole } from "../../stores/useAuthStore";

const LoginStudent = () => {
    /* estado local del formulario: se usa para validar y controlar el boton */
    const [email, setEmail] = useState("");
    const [password, setPassword] = useState("");
    const error = useAuthStore((state) => state.error);
    const isSubmitting = useAuthStore((state) => state.loading);
    const login = useAuthStore((state) => state.login);
    const clearError = useAuthStore((state) => state.clearError);
    const setError = useAuthStore((state) => state.setError);
    const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
    const role = useAuthStore((state) => state.role);

    const navigate = useNavigate();

    // Redirección si ya hay sesión activa
    useEffect(() => {
        if (isAuthenticated && role) {
            navigate(getRedirectPathByRole(role), { replace: true });
        }
    }, [isAuthenticated, role, navigate]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        clearError();

        if (!email.trim() || !password.trim()) {
            setError("Por favor completa el correo y la contraseña.");
            return;
        }

        try {
            const data = await login({ email: email.trim(), password });
            const userRole = data?.user?.role || "user";
            navigate(getRedirectPathByRole(userRole));
        } catch {
            // El mensaje visible lo gestiona el store.
        }
    };

    return (
        <div className="main-container">
            <div className="left-panel">
                <div className="logo-top">
                    <img
                        src={ZorritoLogo}
                        alt="Logo Athena"
                        className="small-logo"
                    />
                    <h1>ATHENA</h1>
                </div>

                <p className="subtitle">Vamos paso por paso.</p>
                <div className="mascot-container">
                    <img
                        src={Zorrito2D}
                        alt="Mascota Athena con gafas"
                        className="mascot"
                    />
                </div>
            </div>

            <div className="right-panel">
                <div className="form-container">
                    <h2>Ingresa a tu cuenta de estudiante ATHENA</h2>
                    <p className="description">
                        Ingresa tu correo o número para continuar.
                    </p>

                    <form onSubmit={handleSubmit}>
                        <input
                            type="email"
                            placeholder="Correo electrónico"
                            className="input-field"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            required
                        />

                        <input
                            type="password"
                            placeholder="Contraseña"
                            className="input-field password-input"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            required
                        />

                        <button
                            type="submit"
                            className="btn-primary"
                            disabled={isSubmitting}
                        >
                            {isSubmitting ? "Ingresando..." : "Iniciar sesión"}
                        </button>
                    </form>

                    {error && <p className="form-error">{error}</p>}

                    <div className="divider">
                        <span>ó</span>
                    </div>

                    <div className="social-buttons">
                        <button className="btn-social" type="button">
                            <i className="fab fa-google"></i> Continuar con
                            Google
                        </button>
                        <button className="btn-social" type="button">
                            <i className="fab fa-facebook-f"></i> Continuar con
                            Facebook
                        </button>
                        <button className="btn-social" type="button">
                            <i className="fab fa-apple"></i> Continuar con Apple
                        </button>
                    </div>

                    <div className="footer-section">
                        <p className="terms-text">
                            Al continuar, aceptas Términos y Condiciones y
                            Política de privacidad
                        </p>

                        <div className="footer-links">
                            <Link to="/register">Crear Cuenta</Link>
                            <Link to="/forgotPassword">
                                ¿Olvidaste la contraseña?
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default LoginStudent;
