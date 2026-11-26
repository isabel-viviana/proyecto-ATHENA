import "./reset_password.css";
import ZorritoLogo from "../../assets/images/Zorrito6.png";
import Zorrito2D from "../../assets/images/Zorrito2D.png";
import { Link, useNavigate, useSearchParams } from "react-router-dom";
import { useState } from "react";
import useAuthStore from "../../stores/useAuthStore";

const ResetPassword = () => {
    const [searchParams] = useSearchParams();
    const oobCode = searchParams.get("oobCode") || "";
    const token = searchParams.get("token") || "";
    const initialEmail = searchParams.get("email") || "";

    const [email, setEmail] = useState(initialEmail);
    const [password, setPassword] = useState("");
    const [confirm, setConfirm] = useState("");
    const [error, setError] = useState("");
    const [success, setSuccess] = useState("");
    const [loading, setLoading] = useState(false);

    const navigate = useNavigate();
    const confirmPasswordResetFlow = useAuthStore(
        (s) => s.confirmPasswordResetFlow
    );

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError("");
        setSuccess("");

        if (!oobCode && !email.trim()) {
            setError("Por favor ingresa tu correo electrónico.");
            return;
        }

        if (password.length < 8) {
            setError("La contraseña debe tener al menos 8 caracteres.");
            return;
        }

        if (password !== confirm) {
            setError("Las contraseñas no coinciden.");
            return;
        }

        if (!oobCode && !token) {
            setError(
                "Falta el código de seguridad de recuperación. Solicita un nuevo enlace desde '¿Olvidaste la contraseña?'."
            );
            return;
        }

        setLoading(true);
        try {
            const res = await confirmPasswordResetFlow({
                oobCode,
                email: email.trim(),
                password,
                passwordConfirmation: confirm,
                token,
            });

            setSuccess(
                res?.message ||
                    "Contraseña restablecida correctamente. Redirigiendo al login..."
            );
            setTimeout(() => {
                navigate("/login", { replace: true });
            }, 2200);
        } catch (err) {
            setError(
                err?.response?.data?.message ||
                    err?.message ||
                    "El enlace de recuperación es inválido o ha expirado."
            );
        } finally {
            setLoading(false);
        }
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
                        Introduce tu nueva contraseña (mínimo 8 caracteres) para continuar.
                    </p>

                    <form className="reset-form" onSubmit={handleSubmit}>
                        {!oobCode && (
                            <input
                                type="email"
                                placeholder="Correo electrónico"
                                className="input-field"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                disabled={loading}
                                required
                            />
                        )}

                        <input
                            type="password"
                            placeholder="Nueva contraseña (mínimo 8 caracteres)"
                            className="input-field"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            disabled={loading}
                            required
                        />

                        <input
                            type="password"
                            placeholder="Confirmar nueva contraseña"
                            className="input-field"
                            value={confirm}
                            onChange={(e) => setConfirm(e.target.value)}
                            disabled={loading}
                            required
                        />

                        {error && <p className="form-error">{error}</p>}

                        {success && (
                            <p
                                style={{
                                    color: "#4ade80",
                                    fontSize: "13px",
                                    marginBottom: "12px",
                                    textAlign: "center",
                                }}
                            >
                                {success}
                            </p>
                        )}

                        <button
                            type="submit"
                            className="btn-primary"
                            disabled={loading}
                        >
                            {loading ? "Guardando..." : "Guardar contraseña"}
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
                            <Link to="/forgotPassword">Solicitar nuevo enlace</Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default ResetPassword;
