import { useEffect, useMemo, useState } from "react";
import { Link } from "react-router-dom";
import api from "../../api/axios";
import "./payments.css";

const formatCop = (amount) => {
    const value = Number(amount);

    if (
        amount === null ||
        amount === undefined ||
        amount === "" ||
        !Number.isFinite(value)
    ) {
        return "Precio no disponible";
    }

    return new Intl.NumberFormat("es-CO", {
        style: "currency",
        currency: "COP",
        maximumFractionDigits: 0,
    }).format(value);
};

const getApiError = (error) => {
    const validationError = Object.values(error.response?.data?.errors ?? {})
        .flat()
        .find(Boolean);

    return (
        validationError ||
        error.response?.data?.message ||
        error.message ||
        "No se pudo completar la solicitud. Inténtalo de nuevo."
    );
};

const Payments = () => {
    const [plans, setPlans] = useState([]);
    const [selectedPlanKey, setSelectedPlanKey] = useState("");
    const [isLoadingPlans, setIsLoadingPlans] = useState(true);
    const [isStartingCheckout, setIsStartingCheckout] = useState(false);
    const [error, setError] = useState("");
    const [requiresLogin, setRequiresLogin] = useState(false);

    useEffect(() => {
        const controller = new AbortController();

        const loadPlans = async () => {
            try {
                const response = await api.get("/payments/plans", {
                    signal: controller.signal,
                });
                const availablePlans = response.data?.data;

                if (!Array.isArray(availablePlans)) {
                    throw new Error("El servidor devolvió una lista de planes inválida.");
                }

                setPlans(availablePlans);
                setSelectedPlanKey(
                    availablePlans.find((plan) => plan.purchasable)?.plan_key ?? "",
                );
            } catch (requestError) {
                if (!controller.signal.aborted) {
                    setError(getApiError(requestError));
                    setRequiresLogin(requestError.response?.status === 401);
                }
            } finally {
                if (!controller.signal.aborted) {
                    setIsLoadingPlans(false);
                }
            }
        };

        loadPlans();

        return () => controller.abort();
    }, []);

    const selectedPlan = useMemo(
        () => plans.find((plan) => plan.plan_key === selectedPlanKey),
        [plans, selectedPlanKey],
    );

    const startCheckout = async (event) => {
        event.preventDefault();
        setError("");
        setRequiresLogin(false);

        if (!selectedPlan?.purchasable) {
            setError("Selecciona un plan disponible para continuar.");
            return;
        }

        if (!localStorage.getItem("athena_token")) {
            setError("Inicia sesión para continuar con el pago.");
            setRequiresLogin(true);
            return;
        }

        setIsStartingCheckout(true);

        try {
            const response = await api.post("/payments/checkout", {
                plan_key: selectedPlan.plan_key,
            });
            const checkoutUrl = response.data?.data?.checkout_url;

            if (typeof checkoutUrl !== "string") {
                throw new Error("El servidor no devolvió el enlace de pago de Wompi.");
            }

            const destination = new URL(checkoutUrl);
            if (
                destination.protocol !== "https:" ||
                destination.hostname !== "checkout.wompi.co"
            ) {
                throw new Error("El servidor devolvió un enlace de pago no válido.");
            }

            window.location.assign(destination.toString());
        } catch (requestError) {
            setError(getApiError(requestError));
            setRequiresLogin(requestError.response?.status === 401);
            setIsStartingCheckout(false);
        }
    };

    return (
        <div className="checkout-wrapper">
            <Link className="checkout-back" to="/subscriptions">
                ← Volver a planes
            </Link>

            <h1 className="checkout-title">Finaliza tu suscripción</h1>

            <form className="checkout-grid" onSubmit={startCheckout}>
                <section className="payment-card" aria-labelledby="payment-title">
                    <div className="payment-card__header">
                        <i className="far fa-credit-card" aria-hidden="true"></i>
                        <h2 id="payment-title">Elige tu plan</h2>
                    </div>

                    {isLoadingPlans ? (
                        <p role="status">Cargando planes disponibles...</p>
                    ) : plans.length === 0 ? (
                        <p>No hay planes disponibles en este momento.</p>
                    ) : (
                        <fieldset className="plan-options" disabled={isStartingCheckout}>
                            <legend className="plan-options__legend">
                                Selecciona una opción
                            </legend>
                            {plans.map((plan) => (
                                <label
                                    className={`plan-option ${
                                        selectedPlanKey === plan.plan_key
                                            ? "plan-option--selected"
                                            : ""
                                    } ${!plan.purchasable ? "plan-option--disabled" : ""}`}
                                    key={plan.plan_key}
                                >
                                    <input
                                        type="radio"
                                        name="plan"
                                        value={plan.plan_key}
                                        checked={selectedPlanKey === plan.plan_key}
                                        disabled={!plan.purchasable}
                                        onChange={() => {
                                            setSelectedPlanKey(plan.plan_key);
                                            setError("");
                                        }}
                                    />
                                    <span className="plan-option__details">
                                        <span className="plan-option__name">
                                            {plan.name}
                                        </span>
                                        <span className="plan-option__duration">
                                            {plan.duration_days} días
                                        </span>
                                    </span>
                                    <span className="plan-option__price">
                                        {formatCop(plan.price_cop)}
                                    </span>
                                    {!plan.purchasable && (
                                        <span className="plan-option__unavailable">
                                            No disponible
                                        </span>
                                    )}
                                </label>
                            ))}
                        </fieldset>
                    )}

                    <div className="payment-secure">
                        <i className="fas fa-shield-alt" aria-hidden="true"></i>
                        <span>
                            El pago se procesa de forma segura en Wompi. Allí podrás
                            elegir Nequi si está habilitado para la cuenta sandbox.
                        </span>
                    </div>
                </section>

                <aside className="summary-card" aria-labelledby="summary-title">
                    <h2 className="summary-card__title" id="summary-title">
                        Resumen de compra
                    </h2>

                    {selectedPlan ? (
                        <>
                            <div className="summary-item">
                                <div>
                                    <h4>{selectedPlan.name}</h4>
                                    <span>
                                        {selectedPlan.duration_days} días de suscripción
                                    </span>
                                </div>
                                <p>{formatCop(selectedPlan.price_cop)}</p>
                            </div>

                            <div className="summary-divider"></div>

                            <div className="summary-total">
                                <span>Total</span>
                                <h3>{formatCop(selectedPlan.price_cop)}</h3>
                            </div>
                        </>
                    ) : (
                        <p>Selecciona un plan disponible para ver el total.</p>
                    )}

                    {error && (
                        <div className="payment-error" role="alert">
                            <p>{error}</p>
                            {requiresLogin && <Link to="/login">Iniciar sesión</Link>}
                        </div>
                    )}

                    <button
                        className="pay-button"
                        type="submit"
                        disabled={
                            isLoadingPlans ||
                            isStartingCheckout ||
                            !selectedPlan?.purchasable
                        }
                    >
                        {isStartingCheckout
                            ? "Conectando con Wompi..."
                            : selectedPlan
                              ? `Continuar al pago · ${formatCop(selectedPlan.price_cop)}`
                              : "Selecciona un plan"}
                    </button>
                </aside>
            </form>
        </div>
    );
};

export default Payments;
