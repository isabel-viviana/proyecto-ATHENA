import "./payments.css";
import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import useAuthStore from "../../stores/useAuthStore";
import { SUBSCRIPTION_PLANS } from "../../services/wompiService";
import WompiCheckoutModal from "../../components/wompi/WompiCheckoutModal";

const Payments = () => {
    const user = useAuthStore((state) => state.user);
    const navigate = useNavigate();
    const [selectedPlan, setSelectedPlan] = useState(SUBSCRIPTION_PLANS[0]);
    const [isWompiOpen, setIsWompiOpen] = useState(false);

    return (
        <>
            <div className="checkout-wrapper">
                <Link to="/subscriptions">← Volver a Mi Suscripción</Link>

                <h1 className="checkout-title">Resumen de Compra (Wompi / Nequi)</h1>

                <div className="checkout-grid">
                    {/*  PLAN SELECTION & PAYMENT  */}
                    <section className="payment-card">
                        <div className="payment-card__header">
                            <i className="fas fa-wallet"></i>
                            <h2>Selecciona tu Plan ATHENA Premium</h2>
                        </div>

                        <div style={{ display: "flex", flexDirection: "column", gap: "12px", marginBottom: "20px" }}>
                            {SUBSCRIPTION_PLANS.map((plan) => {
                                const isSelected = selectedPlan.id === plan.id;
                                return (
                                    <div
                                        key={plan.id}
                                        onClick={() => setSelectedPlan(plan)}
                                        style={{
                                            padding: "16px",
                                            borderRadius: "12px",
                                            border: isSelected
                                                ? "2px solid #6366f1"
                                                : "1px solid rgba(148, 163, 184, 0.2)",
                                            background: isSelected
                                                ? "rgba(99, 102, 241, 0.12)"
                                                : "rgba(15, 23, 42, 0.5)",
                                            cursor: "pointer",
                                            display: "flex",
                                            justifyContent: "space-between",
                                            alignItems: "center",
                                        }}
                                    >
                                        <div>
                                            <strong style={{ color: "#f8fafc", fontSize: "16px" }}>
                                                {plan.name}{" "}
                                                {plan.badge && (
                                                    <span style={{ fontSize: "12px", color: "#38bdf8", marginLeft: "6px" }}>
                                                        {plan.badge}
                                                    </span>
                                                )}
                                            </strong>
                                            <p style={{ color: "#94a3b8", fontSize: "13px", marginTop: "4px" }}>
                                                {plan.description}
                                            </p>
                                        </div>
                                        <div style={{ textAlign: "right", minWidth: "110px" }}>
                                            <strong style={{ color: "#34d399", fontSize: "16px" }}>
                                                {plan.formattedPrice}
                                            </strong>
                                            <span style={{ display: "block", fontSize: "11px", color: "#94a3b8" }}>
                                                {plan.billingText}
                                            </span>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        <div className="payment-secure">
                            <i className="fas fa-shield-alt"></i>
                            Pago 100% seguro procesado por Wompi Bancolombia (Nequi / Tarjetas / PSE)
                        </div>
                    </section>

                    {/*  ORDER  */}
                    <aside className="summary-card">
                        <h2 className="summary-card__title">
                            Detalle del pedido
                        </h2>

                        <div className="summary-item">
                            <div>
                                <h4>{selectedPlan.name}</h4>
                                <span>({selectedPlan.duration_days} días)</span>
                            </div>

                            <p>{selectedPlan.formattedPrice}</p>
                        </div>

                        <div className="summary-item summary-item--muted">
                            <span>Impuestos</span>
                            <span>Incluidos</span>
                        </div>

                        <div className="summary-divider"></div>

                        <div className="summary-total">
                            <span>Total</span>
                            <h3>{selectedPlan.formattedPrice}</h3>
                        </div>

                        <button
                            type="button"
                            className="pay-button"
                            onClick={() => setIsWompiOpen(true)}
                        >
                            Pagar {selectedPlan.formattedPrice} con Wompi
                        </button>
                    </aside>
                </div>
            </div>

            <WompiCheckoutModal
                plan={selectedPlan}
                user={user}
                isOpen={isWompiOpen}
                onClose={() => setIsWompiOpen(false)}
                onSuccess={() => navigate("/subscriptions")}
            />
        </>
    );
};

export default Payments;
