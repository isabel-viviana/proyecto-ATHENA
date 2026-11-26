import { useEffect, useState } from "react";
import "./WompiCheckoutModal.css";
import {
    formatCop,
    openOfficialWompiWidget,
} from "../../services/wompiService";

const WompiCheckoutModal = ({
    checkoutSession,
    isOpen,
    onClose,
    onWidgetFinished,
    onWebCheckoutOpened,
    isWaitingConfirmation = false,
}) => {
    const [isOpeningWidget, setIsOpeningWidget] = useState(false);
    const [widgetError, setWidgetError] = useState("");
    const [webTabOpened, setWebTabOpened] = useState(false);
    const [popupBlocked, setPopupBlocked] = useState(false);

    useEffect(() => {
        if (isOpen) {
            setIsOpeningWidget(false);
            setWidgetError("");
            setWebTabOpened(false);
            setPopupBlocked(false);
        }
    }, [isOpen, checkoutSession?.subscription_id]);

    if (!isOpen || !checkoutSession) return null;

    const checkoutUrl = checkoutSession.checkout_url || "";
    const isValidCheckoutUrl =
        typeof checkoutUrl === "string" &&
        checkoutUrl.startsWith("https://checkout.wompi.co");

    const persistPendingSubscription = () => {
        try {
            sessionStorage.setItem(
                "athena_last_checkout_sub_id",
                String(checkoutSession.subscription_id)
            );
        } catch {
            // ignore storage errors
        }
    };

    const handleLaunchWidget = async () => {
        setIsOpeningWidget(true);
        setWidgetError("");
        persistPendingSubscription();

        try {
            await openOfficialWompiWidget(checkoutSession, (transaction) => {
                setIsOpeningWidget(false);
                if (typeof onWidgetFinished === "function") {
                    onWidgetFinished(checkoutSession.subscription_id, transaction);
                }
            });
        } catch (err) {
            setIsOpeningWidget(false);
            setWidgetError(
                err?.message ||
                    "No se pudo abrir el Widget de Wompi. Utiliza el botón 'Continuar en página de Wompi' a continuación."
            );
        }
    };

    const handleOpenWompiInNewTab = () => {
        setWidgetError("");
        persistPendingSubscription();

        if (!isValidCheckoutUrl) {
            setWidgetError(
                "El servidor no devolvió una URL válida de Wompi Web Checkout."
            );
            return;
        }

        const newTab = window.open(
            checkoutUrl,
            "_blank",
            "noopener,noreferrer"
        );

        if (!newTab) {
            setPopupBlocked(true);
        } else {
            setPopupBlocked(false);
        }

        setWebTabOpened(true);

        if (typeof onWebCheckoutOpened === "function") {
            onWebCheckoutOpened(checkoutSession.subscription_id, !newTab);
        }
    };

    return (
        <div className="wompi-modal-overlay" onClick={onClose}>
            <div
                className="wompi-modal-container"
                onClick={(e) => e.stopPropagation()}
                role="dialog"
                aria-modal="true"
                aria-labelledby="wompi-modal-title"
            >
                {/* CABECERA */}
                <div className="wompi-modal-header">
                    <div className="wompi-brand">
                        <div className="wompi-brand-icon">
                            <i className="fas fa-shield-halved"></i>
                        </div>
                        <div>
                            <div className="wompi-title-row">
                                <h3 id="wompi-modal-title">Checkout Oficial Wompi</h3>
                                <span className="sandbox-badge">
                                    <i className="fas fa-lock"></i> FIRMA SHA-256
                                </span>
                            </div>
                            <p className="wompi-subtitle">
                                Pasarela Bancolombia • Nequi, PSE, Tarjetas y Botón Bancolombia
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        className="wompi-close-btn"
                        onClick={onClose}
                        title="Cerrar"
                    >
                        <i className="fas fa-times"></i>
                    </button>
                </div>

                {/* RESUMEN DEL PLAN Y REFERENCIA REAL DE POSTGRESQL */}
                <div className="wompi-plan-summary">
                    <div className="plan-summary-info">
                        <span className="summary-plan-label">
                            Suscripción #{checkoutSession.subscription_id} • Estado: Pendiente
                        </span>
                        <h4 className="summary-plan-name">{checkoutSession.plan_name}</h4>
                        <span className="summary-plan-duration">
                            <i className="fas fa-calendar-check"></i>{" "}
                            {checkoutSession.duration_days} días de vigencia
                        </span>
                    </div>
                    <div className="plan-summary-price">
                        <span className="summary-price-value">
                            {formatCop(checkoutSession.price_cop)}
                        </span>
                        <span className="summary-price-sub">
                            Moneda: {checkoutSession.currency || "COP"}
                        </span>
                    </div>
                </div>

                {/* CUERPO DEL MODAL */}
                <div className="wompi-checkout-body">
                    <div className="receipt-box" style={{ marginBottom: 0 }}>
                        <div className="receipt-row">
                            <span>Referencia Firmada:</span>
                            <code title={checkoutSession.reference}>
                                {String(checkoutSession.reference).slice(0, 28)}...
                            </code>
                        </div>
                        <div className="receipt-row">
                            <span>Monto en Centavos:</span>
                            <strong>{checkoutSession.amount_in_cents} COP (centavos)</strong>
                        </div>
                        <div className="receipt-row">
                            <span>Medios disponibles en Wompi:</span>
                            <span>Nequi, PSE, Bancolombia, Tarjetas</span>
                        </div>
                    </div>

                    {/* AVISO DE ESPERA CUANDO YA SE ABRIÓ LA PESTAÑA DE WOMPI */}
                    {(webTabOpened || isWaitingConfirmation) && (
                        <div className="sub-waiting-box" role="status">
                            <div className="sub-waiting-box__header">
                                <i className="fas fa-circle-notch fa-spin"></i>
                                <strong>Esperando confirmación de pago en Wompi...</strong>
                            </div>
                            <p className="sub-waiting-box__desc">
                                Completa tu pago en la nueva pestaña de Wompi. Esta pestaña de ATHENA
                                detectará el resultado automáticamente cada 3 segundos y al regresar
                                aquí actualizará tu Plan Actual sin recargar la página.
                            </p>

                            {popupBlocked && isValidCheckoutUrl && (
                                <div className="sub-popup-fallback">
                                    <span>
                                        Tu navegador bloqueó la ventana emergente. Haz clic en el
                                        siguiente enlace directo para abrir Wompi:
                                    </span>
                                    <a
                                        href={checkoutUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="sub-direct-wompi-link"
                                    >
                                        <i className="fas fa-up-right-from-square"></i>
                                        <span>Abrir página de pago de Wompi en nueva pestaña</span>
                                    </a>
                                </div>
                            )}

                            {!popupBlocked && isValidCheckoutUrl && (
                                <div className="sub-popup-fallback">
                                    <span>¿Cerraste la pestaña de Wompi por accidente?</span>
                                    <a
                                        href={checkoutUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="sub-direct-wompi-link"
                                    >
                                        <i className="fas fa-up-right-from-square"></i>
                                        <span>Volver a abrir enlace directo de Wompi</span>
                                    </a>
                                </div>
                            )}
                        </div>
                    )}

                    <div className="sandbox-tip-box" style={{ marginBottom: 0 }}>
                        <i className="fas fa-circle-info"></i>
                        <div>
                            <strong>Datos de Prueba Oficiales en Wompi Sandbox:</strong>
                            <p>
                                Para Nequi en Wompi usa <code>3991111111</code> (Pago Aprobado) o{" "}
                                <code>3992222222</code> (Pago Rechazado). El webhook actualizará tu
                                suscripción automáticamente en PostgreSQL.
                            </p>
                        </div>
                    </div>

                    {widgetError && (
                        <div
                            className="wompi-widget-alert"
                            role="alert"
                            style={{
                                padding: "12px 14px",
                                borderRadius: "12px",
                                background: "rgba(239, 68, 68, 0.14)",
                                border: "1px solid rgba(239, 68, 68, 0.38)",
                                color: "#fca5a5",
                                fontSize: "0.82rem",
                                lineHeight: 1.45,
                            }}
                        >
                            <i
                                className="fas fa-triangle-exclamation"
                                style={{ marginRight: "8px" }}
                            ></i>
                            {widgetError}
                        </div>
                    )}

                    <div style={{ display: "flex", flexDirection: "column", gap: "10px" }}>
                        {/* FLUJO A: WIDGET OFICIAL DE WOMPI */}
                        <button
                            type="button"
                            className="btn-submit-wompi-widget"
                            onClick={handleLaunchWidget}
                            disabled={isOpeningWidget}
                        >
                            {isOpeningWidget ? (
                                <>
                                    <i className="fas fa-circle-notch fa-spin"></i>
                                    <span>Cargando Widget Oficial de Wompi...</span>
                                </>
                            ) : (
                                <>
                                    <i className="fas fa-window-restore"></i>
                                    <span>
                                        Abrir Widget Oficial de Wompi ({formatCop(checkoutSession.price_cop)})
                                    </span>
                                </>
                            )}
                        </button>

                        {/* FLUJO B: CONTINUAR EN PÁGINA DE WOMPI EN NUEVA PESTAÑA */}
                        <button
                            type="button"
                            className="btn-submit-wompi-nequi"
                            onClick={handleOpenWompiInNewTab}
                        >
                            <i className="fas fa-up-right-from-square"></i>
                            <span>Continuar en página de Wompi (Web Checkout)</span>
                        </button>
                    </div>
                </div>

                {/* PIE DEL MODAL */}
                <div className="wompi-modal-footer">
                    <div className="footer-security-tags">
                        <span>
                            <i className="fas fa-certificate"></i> Firma de Integridad Verificada
                        </span>
                        <span>
                            <i className="fas fa-university"></i> Wompi Bancolombia
                        </span>
                        <span>
                            <i className="fas fa-database"></i> Sincronizado con PostgreSQL
                        </span>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default WompiCheckoutModal;
