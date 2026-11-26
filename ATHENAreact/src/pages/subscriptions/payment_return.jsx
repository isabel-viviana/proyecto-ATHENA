import { useEffect, useState } from "react";
import { useLocation, useNavigate } from "react-router-dom";
import "./subscription.css";

/**
 * Vista pública de retorno de Wompi (/payment-return).
 * - Si existe sesión activa en este mismo dominio (athena_token en localStorage),
 *   redirige automáticamente a /suscripciones conservando los query params (?id=...).
 * - Si no existe sesión en este dominio (ej. túnel Cloudflare / ngrok distinto a localhost),
 *   muestra confirmación limpia en tema oscuro e invita a cerrar la pestaña para volver a ATHENA.
 */
const PaymentReturn = () => {
    const location = useLocation();
    const navigate = useNavigate();
    const [closeBlocked, setCloseBlocked] = useState(false);

    const searchParams = new URLSearchParams(location.search);
    const transactionId = searchParams.get("id") || "";
    const environment = searchParams.get("env") || "SANDBOX";

    useEffect(() => {
        try {
            const hasActiveToken = Boolean(localStorage.getItem("athena_token"));
            if (hasActiveToken) {
                navigate(`/suscripciones${location.search}`, { replace: true });
            }
        } catch {
            // Si el acceso a storage está restringido, mostrar la tarjeta pública de confirmación
        }
    }, [location.search, navigate]);

    const handleCloseTab = () => {
        try {
            window.close();
        } catch {
            // ignore
        }
        // Si el navegador impide cerrar la pestaña mediante script, mostrar indicación manual
        setTimeout(() => {
            setCloseBlocked(true);
        }, 250);
    };

    return (
        <div className="sub-return-viewport">
            <div className="sub-return-card">
                <div className="sub-return-icon">
                    <i className="fas fa-circle-check"></i>
                </div>

                <span className="sub-return-badge">
                    <i className="fas fa-shield-halved"></i>
                    PASARELA WOMPI • {environment}
                </span>

                <h1 className="sub-return-title">Operación finalizada en Wompi</h1>

                <p className="sub-return-desc">
                    Operación finalizada en Wompi. Ya puedes cerrar esta pestaña y volver a
                    ATHENA; tu suscripción se reflejará automáticamente.
                </p>

                {transactionId && (
                    <div className="sub-return-tx-box">
                        <span className="sub-return-tx-label">ID de Transacción Wompi</span>
                        <code className="sub-return-tx-code">{transactionId}</code>
                    </div>
                )}

                <div className="sub-return-actions">
                    <button
                        type="button"
                        className="sub-return-close-btn"
                        onClick={handleCloseTab}
                    >
                        <i className="fas fa-xmark"></i>
                        <span>Cerrar esta pestaña</span>
                    </button>
                </div>

                {closeBlocked && (
                    <p className="sub-return-hint">
                        Por seguridad de tu navegador, puedes cerrar esta pestaña manualmente
                        con <strong>Ctrl + W</strong> o haciendo clic en la <strong>X</strong> de la pestaña.
                    </p>
                )}
            </div>
        </div>
    );
};

export default PaymentReturn;
