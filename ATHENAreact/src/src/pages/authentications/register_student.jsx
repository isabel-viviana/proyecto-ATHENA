import "./register_student.css";
import ZorritoLogo from "../../assets/images/Zorrito6.png";
import Zorrito2D from "../../assets/images/Zorrito2D.png";
import { Link, useNavigate } from "react-router-dom";
import { useState } from "react";

const RegisterStudent = () => {
    /* estado local del formulario (correo) */
    const [email, setEmail] = useState("");
    const [error, setError] = useState("");
    const [isSubmitting, setIsSubmitting] = useState(false);

    /* ruta de salida luego del submit */
    const navigate = useNavigate();

    const handleSubmit = (e) => {
        /* evita recarga del navegador */
        e.preventDefault();
        setError("");

        /* validacion minima para no simular submits vacios */
        if (!email.trim()) {
            setError("Ingresa tu correo electrónico.");
            return;
        }

        /* importante: deshabilita el boton mientras “carga” */
        setIsSubmitting(true);
        setTimeout(() => {
            setIsSubmitting(false);
            navigate("/dashboard");
        }, 600);
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

                        <button
                            type="submit"
                            className="btn-primary"
                            disabled={isSubmitting}
                        >
                            {isSubmitting ? "Registrando..." : "Continuar"}
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
