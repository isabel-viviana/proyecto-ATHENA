import "./login_student.css";
import Zorrito2D from "../../assets/images/Zorrito2D.png";
import ZorritoLogo from "../../assets/images/Zorrito6.png";
import { Link, useNavigate } from "react-router-dom";
import { useState } from "react";
import { studentLogin } from "../../api/studentQuestions";

const LoginStudent = () => {
    /* estado local del formulario: se usa para validar y controlar el boton */
    /* importante: estos estados evitan errores tipicos de submit duplicado */
    const [email, setEmail] = useState("");
    const [password, setPassword] = useState("");
    const [error, setError] = useState("");
    const [isSubmitting, setIsSubmitting] = useState(false);

    /* navegacion post submit: mantiene la vista declarativa con react-router */
    const navigate = useNavigate();

    const handleSubmit = async (e) => {
        /* evita recarga del navegador para mantener el SPA */
        e.preventDefault();
        setError("");

        /* validacion minima antes del submit */
        if (!email.trim() || !password.trim()) {
            setError("Por favor completa el correo y la contraseña.");
            return;
        }

        setIsSubmitting(true);
        try {
            const res = await studentLogin(email.trim(), password);
            if (res && res.success && res.token) {
                localStorage.setItem("athena_token", res.token);
                localStorage.setItem("athena_student", JSON.stringify(res.student));
                navigate("/practices");
            } else {
                setError(res?.message || "No se pudo verificar la cuenta.");
            }
        } catch (err) {
            console.error("Error en login de estudiante:", err);
            setError(
                err?.response?.data?.message ||
                "Credenciales no válidas. Verifica tu correo y contraseña."
            );
        } finally {
            setIsSubmitting(false);
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
