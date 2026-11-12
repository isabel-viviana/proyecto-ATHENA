import React, { useState } from "react";
import "./WompiCheckoutModal.css";
import {
    WOMPI_CONFIG,
    simulateNequiSandbox,
    openWompiCheckoutWidget,
} from "../../services/wompiService";

const WompiCheckoutModal = ({
    plan,
    user,
    isOpen,
    onClose,
    onSuccess,
}) => {
    if (!isOpen || !plan) return null;

    const [activeTab, setActiveTab] = useState("nequi"); // 'nequi' | 'widget'
    const [phoneNumber, setPhoneNumber] = useState(WOMPI_CONFIG.testNumbers.nequiApproved);
    const [step, setStep] = useState("input"); // 'input' | 'processing' | 'approved' | 'failed'
    const [statusMessage, setStatusMessage] = useState("");
    const [transactionResult, setTransactionResult] = useState(null);
    const [isWidgetLoading, setIsWidgetLoading] = useState(false);

    // Seleccionar número de prueba rápido
    const handleSelectTestPhone = (num) => {
        setPhoneNumber(num);
        setStatusMessage("");
        setStep("input");
    };

    // Procesar pago Nequi Sandbox
    const handlePayNequi = async (e) => {
        if (e) e.preventDefault();
        setStep("processing");
        setStatusMessage("Enviando notificación push a tu app Nequi...");

        try {
            const res = await simulateNequiSandbox({
                plan,
                phoneNumber,
                user,
            });

            if (res.status === "APPROVED") {
                setTransactionResult(res.transaction);
                setStep("approved");
                if (onSuccess) onSuccess(res.subscription, res.transaction);
            } else if (res.status === "DECLINED") {
                setStatusMessage(res.error || "La transacción fue declinada en Nequi Sandbox.");
                setStep("failed");
            } else {
                setStatusMessage(res.error || "Ocurrió un error al procesar el pago con Wompi.");
                setStep("failed");
            }
        } catch (err) {
            setStatusMessage("Error al comunicar con la pasarela Wompi Sandbox.");
            setStep("failed");
        }
    };

    // Abrir Widget oficial de Wompi
    const handleOpenOfficialWidget = async () => {
        setIsWidgetLoading(true);
        setStatusMessage("");
        try {
            await openWompiCheckoutWidget({
                plan,
                user,
                onApproved: (sub, tx) => {
                    setTransactionResult(tx);
                    setStep("approved");
                    setIsWidgetLoading(false);
                    if (onSuccess) onSuccess(sub, tx);
                },
                onDeclined: (tx) => {
                    setStatusMessage("Transacción rechazada en la pasarela Wompi.");
                    setStep("failed");
                    setIsWidgetLoading(false);
                },
                onError: (tx) => {
                    setStatusMessage("Error durante la transacción en Wompi.");
                    setStep("failed");
                    setIsWidgetLoading(false);
                },
                onClose: () => {
                    setIsWidgetLoading(false);
                },
            });
        } catch (err) {
            setIsWidgetLoading(false);
            setStatusMessage(
                "No se pudo cargar el Widget externo de Wompi. Puedes usar la pestaña de Nequi Sandbox directamente."
            );
        }
    };

    const handleModalClose = () => {
        setStep("input");
        setStatusMessage("");
        setTransactionResult(null);
        onClose();
    };

    return (
        <div className="wompi-modal-overlay" onClick={handleModalClose}>
            <div
                className="wompi-modal-container"
                onClick={(e) => e.stopPropagation()}
                role="dialog"
                aria-modal="true"
            >
                {/* MODAL HEADER */}
                <div className="wompi-modal-header">
                    <div className="wompi-brand">
                        <div className="wompi-brand-icon">
                            <i className="fas fa-wallet"></i>
                        </div>
                        <div>
                            <div className="wompi-title-row">
                                <h3>Pasarela Wompi</h3>
                                <span className="sandbox-badge">
                                    <i className="fas fa-flask"></i> SANDBOX
                                </span>
                            </div>
                            <p className="wompi-subtitle">
                                Bancolombia • Pagos Seguros en Colombia
                            </p>
                        </div>
                    </div>
                    <button
                        className="wompi-close-btn"
                        onClick={handleModalClose}
                        title="Cerrar"
                    >
                        <i className="fas fa-times"></i>
                    </button>
                </div>

                {/* PLAN SUMMARY HEADER */}
                <div className="wompi-plan-summary">
                    <div className="plan-summary-info">
                        <span className="summary-plan-label">Plan Seleccionado</span>
                        <h4 className="summary-plan-name">{plan.name}</h4>
                        <span className="summary-plan-duration">
                            <i className="fas fa-clock"></i> {plan.duration_days} días de acceso ilimitado
                        </span>
                    </div>
                    <div className="plan-summary-price">
                        <span className="summary-price-value">{plan.formattedPrice}</span>
                        <span className="summary-price-sub">{plan.billingText}</span>
                    </div>
                </div>

                {/* PASO: APROBADO EXITOSO */}
                {step === "approved" && transactionResult ? (
                    <div className="wompi-state-approved">
                        <div className="approved-icon-wrapper">
                            <i className="fas fa-check-circle"></i>
                        </div>
                        <h3 className="approved-title">¡Pago Exitoso con Wompi!</h3>
                        <p className="approved-desc">
                            Tu suscripción a <strong>{plan.name}</strong> ha sido activada correctamente mediante <strong>Nequi Sandbox</strong>.
                        </p>

                        <div className="receipt-box">
                            <div className="receipt-row">
                                <span>Referencia Wompi:</span>
                                <strong>{transactionResult.reference}</strong>
                            </div>
                            <div className="receipt-row">
                                <span>ID Transacción:</span>
                                <code>{transactionResult.id}</code>
                            </div>
                            <div className="receipt-row">
                                <span>Método de Pago:</span>
                                <span>{transactionResult.paymentMethod || "Nequi Sandbox"}</span>
                            </div>
                            <div className="receipt-row">
                                <span>Total Pagado:</span>
                                <strong>{plan.formattedPrice}</strong>
                            </div>
                            <div className="receipt-row">
                                <span>Estado:</span>
                                <span className="status-tag-approved">
                                    <i className="fas fa-shield-alt"></i> APROBADA
                                </span>
                            </div>
                        </div>

                        <div className="approved-actions">
                            <button
                                className="btn-wompi-confirm"
                                onClick={handleModalClose}
                            >
                                <i className="fas fa-arrow-right"></i> Continuar a mi Perfil
                            </button>
                        </div>
                    </div>
                ) : null}

                {/* PASO: PROCESANDO NEQUI PUSH */}
                {step === "processing" ? (
                    <div className="wompi-state-processing">
                        <div className="nequi-phone-animation">
                            <div className="phone-screen">
                                <div className="nequi-logo-badge">Nequi</div>
                                <div className="spinner-glow"></div>
                                <p className="phone-notification-title">
                                    Solicitud de pago ATHENA
                                </p>
                                <p className="phone-notification-amount">
                                    {plan.formattedPrice}
                                </p>
                                <span className="phone-notification-phone">
                                    Enviado al +57 {phoneNumber}
                                </span>
                            </div>
                        </div>
                        <h4 className="processing-title">Esperando confirmación en Nequi</h4>
                        <p className="processing-sub">
                            Simulando notificación PUSH al celular de pruebas en Wompi Sandbox...
                        </p>
                    </div>
                ) : null}

                {/* PASO: FALLIDO / RECHAZADO */}
                {step === "failed" ? (
                    <div className="wompi-state-failed">
                        <div className="failed-icon-wrapper">
                            <i className="fas fa-exclamation-triangle"></i>
                        </div>
                        <h3 className="failed-title">Transacción No Completada</h3>
                        <p className="failed-desc">{statusMessage}</p>

                        <div className="sandbox-tip-box">
                            <i className="fas fa-info-circle"></i>
                            <div>
                                <strong>Consejo de Sandbox Nequi:</strong>
                                <p>
                                    Usa el número <code>3991111111</code> para simular una transacción exitosa y aprobada.
                                </p>
                            </div>
                        </div>

                        <div className="failed-actions">
                            <button
                                className="btn-wompi-retry"
                                onClick={() => handleSelectTestPhone(WOMPI_CONFIG.testNumbers.nequiApproved)}
                            >
                                <i className="fas fa-redo"></i> Probar con 3991111111 (Aprobado)
                            </button>
                            <button
                                className="btn-wompi-cancel"
                                onClick={handleModalClose}
                            >
                                Cancelar
                            </button>
                        </div>
                    </div>
                ) : null}

                {/* PASO: FORMULARIO DE PAGO */}
                {step === "input" ? (
                    <div className="wompi-checkout-body">
                        {/* TABS SELECTOR */}
                        <div className="wompi-tabs">
                            <button
                                type="button"
                                className={`wompi-tab-btn ${activeTab === "nequi" ? "active" : ""}`}
                                onClick={() => setActiveTab("nequi")}
                            >
                                <i className="fas fa-mobile-alt"></i> Nequi (Sandbox)
                            </button>
                            <button
                                type="button"
                                className={`wompi-tab-btn ${activeTab === "widget" ? "active" : ""}`}
                                onClick={() => setActiveTab("widget")}
                            >
                                <i className="fas fa-credit-card"></i> Widget Oficial Wompi
                            </button>
                        </div>

                        {/* TAB 1: NEQUI SANDBOX */}
                        {activeTab === "nequi" ? (
                            <div className="tab-nequi-content">
                                {/* SANDBOX INSTRUCTIONS CARD */}
                                <div className="nequi-sandbox-notice">
                                    <div className="notice-header">
                                        <i className="fas fa-flask"></i>
                                        <span>Números de Prueba Oficiales Wompi Sandbox</span>
                                    </div>
                                    <div className="test-buttons-row">
                                        <button
                                            type="button"
                                            className={`test-chip test-chip--approved ${phoneNumber === WOMPI_CONFIG.testNumbers.nequiApproved ? "selected" : ""}`}
                                            onClick={() => handleSelectTestPhone(WOMPI_CONFIG.testNumbers.nequiApproved)}
                                        >
                                            <i className="fas fa-check"></i> 3991111111 (Aprobada)
                                        </button>
                                        <button
                                            type="button"
                                            className={`test-chip test-chip--declined ${phoneNumber === WOMPI_CONFIG.testNumbers.nequiDeclined ? "selected" : ""}`}
                                            onClick={() => handleSelectTestPhone(WOMPI_CONFIG.testNumbers.nequiDeclined)}
                                        >
                                            <i className="fas fa-times"></i> 3992222222 (Rechazada)
                                        </button>
                                    </div>
                                </div>

                                <form onSubmit={handlePayNequi} className="nequi-form">
                                    <div className="form-group-wompi">
                                        <label htmlFor="nequi-phone-input">
                                            Número de Celular Nequi
                                        </label>
                                        <div className="input-prefix-wrapper">
                                            <span className="prefix-tag">🇨🇴 +57</span>
                                            <input
                                                id="nequi-phone-input"
                                                type="text"
                                                value={phoneNumber}
                                                onChange={(e) => setPhoneNumber(e.target.value)}
                                                placeholder="Ej: 3991111111"
                                                className="input-nequi-phone"
                                                required
                                            />
                                        </div>
                                        <span className="input-hint">
                                            En Sandbox, Nequi requiere números que inicien en 399.
                                        </span>
                                    </div>

                                    <div className="payment-guarantee-wompi">
                                        <i className="fas fa-lock"></i>
                                        <span>
                                            Procesado de forma segura mediante la API de Wompi Bancolombia.
                                        </span>
                                    </div>

                                    <button
                                        type="submit"
                                        className="btn-submit-wompi-nequi"
                                    >
                                        <i className="fas fa-bolt"></i> Pagar {plan.formattedPrice} con Nequi
                                    </button>
                                </form>
                            </div>
                        ) : null}

                        {/* TAB 2: WIDGET OFICIAL */}
                        {activeTab === "widget" ? (
                            <div className="tab-widget-content">
                                <div className="widget-explainer">
                                    <div className="widget-icon-banner">
                                        <i className="fas fa-external-link-alt"></i>
                                    </div>
                                    <h4>Widget Checkout Oficial de Wompi</h4>
                                    <p>
                                        Abre la ventana modal flotante provista directamente por Wompi Bancolombia conectada a su entorno Sandbox.
                                    </p>
                                    <ul className="widget-features-list">
                                        <li>
                                            <i className="fas fa-check-circle"></i> Nequi (Sandbox con número 3991111111)
                                        </li>
                                        <li>
                                            <i className="fas fa-check-circle"></i> Tarjetas de crédito/débito de prueba (4242...)
                                        </li>
                                        <li>
                                            <i className="fas fa-check-circle"></i> Botón Bancolombia y PSE Sandbox
                                        </li>
                                    </ul>
                                </div>

                                {statusMessage ? (
                                    <p className="widget-error-text">{statusMessage}</p>
                                ) : null}

                                <button
                                    type="button"
                                    className="btn-submit-wompi-widget"
                                    onClick={handleOpenOfficialWidget}
                                    disabled={isWidgetLoading}
                                >
                                    {isWidgetLoading ? (
                                        <>
                                            <i className="fas fa-circle-notch fa-spin"></i> Cargando pasarela Wompi...
                                        </>
                                    ) : (
                                        <>
                                            <i className="fas fa-shield-alt"></i> Abrir Widget Wompi Sandbox ({plan.formattedPrice})
                                        </>
                                    )}
                                </button>
                            </div>
                        ) : null}
                    </div>
                ) : null}

                {/* MODAL FOOTER */}
                <div className="wompi-modal-footer">
                    <div className="footer-security-tags">
                        <span><i className="fas fa-certificate"></i> Encriptación 256-bit</span>
                        <span><i className="fas fa-university"></i> Grupo Bancolombia</span>
                        <span><i className="fas fa-bolt"></i> Moneda: COP</span>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default WompiCheckoutModal;
