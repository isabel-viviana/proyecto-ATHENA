import "./subscription.css";
import { useState, useEffect } from "react";
import { Link } from "react-router-dom";
import useAuthStore from "../../stores/useAuthStore";
import {
    SUBSCRIPTION_PLANS,
    getActiveSubscription,
    getPaymentHistory,
} from "../../services/wompiService";
import WompiCheckoutModal from "../../components/wompi/WompiCheckoutModal";

const Subscription = () => {
    const user = useAuthStore((state) => state.user);
    const [activeSub, setActiveSub] = useState(() => getActiveSubscription());
    const [history, setHistory] = useState(() => getPaymentHistory());
    const [selectedPlan, setSelectedPlan] = useState(SUBSCRIPTION_PLANS[0]);
    const [isWompiOpen, setIsWompiOpen] = useState(false);

    useEffect(() => {
        const refresh = () => {
            setActiveSub(getActiveSubscription());
            setHistory(getPaymentHistory());
        };
        window.addEventListener("athena_subscription_updated", refresh);
        return () => window.removeEventListener("athena_subscription_updated", refresh);
    }, []);

    const handleOpenWompi = (plan) => {
        setSelectedPlan(plan || SUBSCRIPTION_PLANS[0]);
        setIsWompiOpen(true);
    };

    return (
        <>
            <div className="main__content">
                {/*  HEADER  */}
                <section className="page-header">
                    <div className="page-header__info">
                        <h1>Mi Suscripción</h1>
                        <p>
                            Administra tu plan y revisa tu historial de pagos con Wompi / Nequi.
                        </p>
                    </div>
                </section>

                {/*  PLAN CARD  */}
                <section className="subscription-card">
                    <div className="subscription-card__top">
                        <div className="subscription-card__plan">
                            <div className="subscription-card__badges">
                                <span className="plan-badge">
                                    <i className="fas fa-crown"></i>
                                    {(activeSub?.planName || "PLAN PREMIUM").toUpperCase()}
                                </span>

                                <span className="status-badge">
                                    <i className="fas fa-circle"></i>
                                    {activeSub?.status === "active" ? "Activo" : "Inactivo"}
                                </span>
                            </div>

                            <h2>Facturación {activeSub?.period?.toLowerCase() || "mensual"}</h2>

                            <p>
                                Próximo cobro:{" "}
                                <span>{activeSub?.formattedEndDate || "15 de Mayo, 2026"}</span>{" "}
                                ({activeSub?.formattedPrice || "$29.900 COP"})
                            </p>
                        </div>

                        <div className="subscription-card__actions">
                            <button
                                type="button"
                                className="btn btn--primary"
                                onClick={() => handleOpenWompi(SUBSCRIPTION_PLANS.find((p) => p.id === activeSub?.planId) || SUBSCRIPTION_PLANS[0])}
                            >
                                Pagar con Wompi / Nequi
                            </button>

                            <Link to="/payments">
                                <button className="btn btn--secondary" type="button">
                                    Cambiar Plan
                                </button>
                            </Link>
                        </div>
                    </div>

                    <div className="subscription-card__bottom">
                        <div className="subscription-stat">
                            <span className="subscription-stat__label">
                                Plan Actual
                            </span>

                            <h3>{activeSub?.planName || "Premium Mensual"}</h3>
                        </div>

                        <div className="subscription-stat">
                            <span className="subscription-stat__label">
                                Renovación
                            </span>

                            <h3>{activeSub?.formattedEndDate || "15 Mayo"}</h3>
                        </div>

                        <div className="subscription-stat">
                            <span className="subscription-stat__label">
                                Método
                            </span>

                            <h3>{activeSub?.paymentMethod || "Nequi (Wompi Sandbox)"}</h3>
                        </div>
                    </div>
                </section>

                {/*  PAYMENT HISTORY  */}
                <section className="history-card">
                    <div className="history-card__header">
                        <h2>Historial de Pagos</h2>
                        <span>Últimos movimientos (Wompi)</span>
                    </div>

                    {history.map((item) => (
                        <div className="payment-item" key={item.id}>
                            <div className="payment-item__left">
                                <div className="payment-icon">
                                    <i className="fas fa-receipt"></i>
                                </div>

                                <div className="payment-info">
                                    <h3>{item.date}</h3>
                                    <p>
                                        {item.planName} • {item.method} (Ref: {item.reference})
                                    </p>
                                </div>
                            </div>

                            <div className="payment-item__right">
                                <h4>{item.formattedAmount}</h4>

                                <span className="payment-status">{item.status}</span>
                            </div>
                        </div>
                    ))}
                </section>
            </div>

            <WompiCheckoutModal
                plan={selectedPlan}
                user={user}
                isOpen={isWompiOpen}
                onClose={() => setIsWompiOpen(false)}
                onSuccess={(sub) => setActiveSub(sub)}
            />
        </>
    );
};

export default Subscription;
