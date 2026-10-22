import "./register_student.css";
import ZorritoLogo from "../../assets/images/Zorrito6.png";
import Zorrito2D from "../../assets/images/Zorrito2D.png";
import { Link, useNavigate } from "react-router-dom";
import { useState } from "react";
import { useAuthStore } from "../../stores/useAuthStore";

const RegisterStudent = () => {
    const [fullName, setFullName] = useState("");
    const [email, setEmail] = useState("");
    const [password, setPassword] = useState("");
    const [passwordConfirmation, setPasswordConfirmation] = useState("");
    const error = useAuthStore((state) => state.error);
    const isSubmitting = useAuthStore((state) => state.loading);
    const register = useAuthStore((state) => state.register);
    const clearError = useAuthStore((state) => state.clearError);
    const setError = useAuthStore((state) => state.setError);

    const navigate = useNavigate();

    const handleSubmit = async (e) => {
        e.preventDefault();
        clearError();

        if (password !== passwordConfirmation) {
            setError("Las contraseñas no coinciden.");
            return;
        }

        try {
            await register({
                full_name: fullName.trim(),
                email: email.trim(),
                password,
                password_confirmation: passwordConfirmation,
            });
            navigate("/dashboard");
        } catch {
            // El mensaje visible lo gestiona el store.
        }
    };

    return (
        <div className="main-container">
            <div className="left-panel">
                <div className="logo-top">
                    <img src={ZorritoLogo} alt="Logo" className="small-logo" />
                    <h1>ATHENA</h1>
                </div>

                <p className="subtitle">Vamos paso por paso.</p>
                <div className="mascot-container">
                    <img
                        src={Zorrito2D}
                        alt="Mascota Athena"
                        className="mascot"
                    />
                </div>
            </div>

            <div className="right-panel">
                <div className="form-container">
                    <h2>Crear cuenta estudiante</h2>
                    <p className="description">
                        Completa tus datos para crear tu cuenta.
                    </p>

                    <form onSubmit={handleSubmit}>
                        <input
                            type="text"
                            placeholder="Nombre completo"
                            className="input-field"
                            value={fullName}
                            onChange={(e) => setFullName(e.target.value)}
                            autoComplete="name"
                            maxLength={255}
                            required
                        />

                        <input
                            type="email"
                            placeholder="Correo electrónico"
                            className="input-field"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            autoComplete="email"
                            required
                        />

                        <input
                            type="password"
                            placeholder="Contraseña (mínimo 8 caracteres)"
                            className="input-field"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            autoComplete="new-password"
                            minLength={8}
                            required
                        />

                        <input
                            type="password"
                            placeholder="Confirma tu contraseña"
                            className="input-field"
                            value={passwordConfirmation}
                            onChange={(e) => setPasswordConfirmation(e.target.value)}
                            autoComplete="new-password"
                            minLength={8}
                            required
                        />

                        <button
                            type="submit"
                            className="btn-primary"
                            disabled={isSubmitting}
                        >
                            {isSubmitting ? "Creando cuenta..." : "Crear cuenta"}
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

                    <p className="footer-text">
                        Al continuar, aceptas Términos y Condiciones y Política
                        de privacidad
                    </p>

                    <div className="footer-links">
                        <Link to="/login">Ya tengo cuenta</Link>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default RegisterStudent;
