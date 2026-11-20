import "./forgot_password.css";
import { useState } from "react";
import { Link } from "react-router-dom";
import useAuthStore from "../../stores/useAuthStore";

const ForgotPassword = () => {
    const [email, setEmail] = useState("");
    const [loading, setLoading] = useState(false);
    const [errorMsg, setErrorMsg] = useState("");
    const [successMsg, setSuccessMsg] = useState("");

    const requestPasswordReset = useAuthStore((s) => s.requestPasswordReset);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setErrorMsg("");
        setSuccessMsg("");

        const trimmedEmail = email.trim();
        if (!trimmedEmail) {
            setErrorMsg("Por favor ingresa tu correo electrónico.");
            return;
        }

        setLoading(true);
        try {
            const res = await requestPasswordReset(trimmedEmail);
            setSuccessMsg(
                res?.message ||
                    "Hemos enviado un enlace de recuperación a tu correo electrónico. Revisa tu bandeja de entrada y la carpeta de spam."
            );
            setEmail("");
        } catch (err) {
            setErrorMsg(
                err?.response?.data?.message ||
                    err?.message ||
                    "No fue posible procesar la solicitud de recuperación."
            );
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="forgot-page">
            <div className="forgot-card">
                <h2>Recuperar contraseña</h2>
                <p>
                    Introduce tu correo electrónico y te enviaremos un enlace
                    para restablecer tu contraseña.
                </p>

                {errorMsg && (
                    <div
                        style={{
                            padding: "12px 14px",
                            borderRadius: "10px",
                            marginBottom: "16px",
                            fontSize: "13px",
                            background: "rgba(239, 68, 68, 0.15)",
                            border: "1px solid rgba(239, 68, 68, 0.35)",
                            color: "#f87171",
                            textAlign: "left",
                        }}
                    >
                        <i
                            className="fas fa-triangle-exclamation"
                            style={{ marginRight: "8px" }}
                        ></i>
                        {errorMsg}
                    </div>
                )}

                {successMsg && (
                    <div
                        style={{
                            padding: "12px 14px",
                            borderRadius: "10px",
                            marginBottom: "16px",
                            fontSize: "13px",
                            background: "rgba(34, 197, 94, 0.15)",
                            border: "1px solid rgba(34, 197, 94, 0.35)",
                            color: "#4ade80",
                            textAlign: "left",
                        }}
                    >
                        <i
                            className="fas fa-circle-check"
                            style={{ marginRight: "8px" }}
                        ></i>
                        {successMsg}
                    </div>
                )}

                <form onSubmit={handleSubmit} className="forgot-form">
                    <input
                        type="email"
                        placeholder="Correo electrónico"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        className="input-field"
                        disabled={loading}
                        required
                    />

                    <button
                        type="submit"
                        className="btn-primary"
                        disabled={loading}
                    >
                        {loading ? "Enviando enlace..." : "Enviar enlace"}
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
