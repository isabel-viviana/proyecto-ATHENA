import "./reset_password.css";
import ZorritoLogo from "../../assets/images/Zorrito6.png";
import Zorrito2D from "../../assets/images/Zorrito2D.png";
import { Link, useNavigate } from "react-router-dom";
import { useState } from "react";

const ResetPassword = () => {
    /* flujo de restablecimiento: validaciones sincronas antes de redirigir */
    /* importante: aqui solo validamos en cliente; el submit redirige a login */
    const [password, setPassword] = useState("");
    const [confirm, setConfirm] = useState("");
    const [error, setError] = useState("");
    const navigate = useNavigate();

    const handleSubmit = (e) => {
        e.preventDefault();
        setError("");

        if (password.length < 8) {
            setError("La contraseña debe tener al menos 8 caracteres");
            return;
        }

        if (password !== confirm) {
            setError("Las contraseñas no coinciden");
            return;
        }

        /* importante: valida antes de redirigir; no hay backend en esta demo */
        navigate("/login");
    };

    return (
        <div className="main-container reset-container">
            <div className="left-panel">
                <div className="logo-top">
                    <img
                        src={ZorritoLogo}
                        alt="Logo Athena"
                        className="small-logo"
                    />
                    <h1>ATHENA</h1>
                </div>

                <p className="subtitle">Recupera el acceso a tu cuenta</p>
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
                    <h2>Restablecer contraseña</h2>
                    <p className="description">
                        Introduce tu nueva contraseña para continuar.
                    </p>

                    <form className="reset-form" onSubmit={handleSubmit}>
                        <input
                            type="password"
                            placeholder="Nueva contraseña"
                            className="input-field"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            required
                        />

                        <input
                            type="password"
                            placeholder="Confirmar nueva contraseña"
                            className="input-field"
                            value={confirm}
                            onChange={(e) => setConfirm(e.target.value)}
                            required
                        />

                        {error && <p className="form-error">{error}</p>}

                        <button type="submit" className="btn-primary">
                            Guardar contraseña
                        </button>
                    </form>

                    <div className="divider">
                        <span>ó</span>
                    </div>

                    <div className="footer-section">
                        <p className="terms-text">
                            ¿Ya recuerdas tu contraseña?
                        </p>
                        <div className="footer-links">
                            <Link to="/login">Volver al login</Link>
                            <Link to="/register">Crear Cuenta</Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default ResetPassword;
