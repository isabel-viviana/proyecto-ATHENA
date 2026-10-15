import "./forgot_password.css";
import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";

const ForgotPassword = () => {
    /* vista simple: recolecta correo y navega al reset (sin request real) */
    /* estado local para controlar el input del correo */
    const [email, setEmail] = useState("");

    /* navigation del flujo: esta vista no hace request real */
    const navigate = useNavigate();

    const handleSubmit = (e) => {
        /* evita recarga; el submit solo nos manda a la pantalla de reset */
        e.preventDefault();
        navigate("/reset-password");
    };

    return (
        <div className="forgot-page">
            <div className="forgot-card">
                <h2>Recuperar contraseña</h2>
                <p>
                    Introduce tu correo electrónico y te enviaremos un enlace
                    para restablecer tu contraseña.
                </p>

                <form onSubmit={handleSubmit} className="forgot-form">
                    <input
                        type="email"
                        placeholder="Correo electrónico"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        className="input-field"
                        required
                    />

                    <button type="submit" className="btn-primary">
                        Enviar enlace
                    </button>
                </form>

                <div className="forgot-footer">
                    <Link to="/login">Volver al login</Link>
                </div>
            </div>
        </div>
    );
};

export default ForgotPassword;
