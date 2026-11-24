import { useCallback, useEffect, useRef, useState } from "react";
import { useSearchParams } from "react-router-dom";
import "./subscription.css";
import {
    createWompiCheckoutSession,
    fetchWompiHistory,
    fetchWompiPlans,
    fetchWompiSubscriptionStatus,
    formatCop,
    formatDateEs,
} from "../../services/wompiService";
import WompiCheckoutModal from "../../components/wompi/WompiCheckoutModal";

const DEFAULT_FEATURES_BY_KEY = {
    monthly: [
        "Simulacros ilimitados Saber 11 en todas las áreas",
        "Tutor IA ATHENA 24/7 con explicaciones paso a paso",
        "Diagnóstico y analítica detallada por competencias",
        "Banco oficial de preguntas contextualizadas ICFES",
    ],
    quarterly: [
        "Todo lo incluido en el Plan Mensual durante 90 días",
        "Entrenamiento continuo con seguimiento de progreso",
        "Simulacros completos cronometrados con percentil",
        "Prioridad en respuestas y análisis de debilidades con IA",
    ],
    annual: [
        "Acceso total e ilimitado durante 365 días",
        "Todas las herramientas de Tutor IA, Prácticas y Simulacros",
        "Histórico completo de evolución y proyección de puntaje",
        "Máximo ahorro por mes frente a la tarifa mensual",
    ],
};

// Polling cada 3 segundos hasta por 3 minutos (60 intentos)
const POLL_INTERVAL_MS = 3000;
const MAX_POLL_ATTEMPTS = 60;

const Subscription = () => {
    const [searchParams, setSearchParams] = useSearchParams();

    const [plans, setPlans] = useState([]);
    const [activeSub, setActiveSub] = useState(null);
    const [history, setHistory] = useState([]);
    const [loading, setLoading] = useState(true);
    const [startingPlanKey, setStartingPlanKey] = useState("");
    const [error, setError] = useState("");

    // Sesión de checkout creada en PostgreSQL para el modal
    const [checkoutSession, setCheckoutSession] = useState(null);
    const [isModalOpen, setIsModalOpen] = useState(false);

    // ID de suscripción pendiente que se monitorea en vivo cada 3s y al recuperar foco
    const [pendingSubscriptionId, setPendingSubscriptionId] = useState(() => {
        try {
            const raw = sessionStorage.getItem("athena_last_checkout_sub_id");
            return raw ? Number(raw) : null;
        } catch {
            return null;
        }
    });

    // Banner de verificación en tiempo real
    const [verificationBanner, setVerificationBanner] = useState(null);
    const [isPolling, setIsPolling] = useState(false);

    const pollingTimerRef = useRef(null);
    const pollAttemptRef = useRef(0);
    const isCheckingRef = useRef(false);

    const stopPolling = useCallback(() => {
        if (pollingTimerRef.current) {
            clearTimeout(pollingTimerRef.current);
            pollingTimerRef.current = null;
        }
        setIsPolling(false);
    }, []);

    const clearStoredPendingSub = useCallback(() => {
        try {
            sessionStorage.removeItem("athena_last_checkout_sub_id");
        } catch {
            // ignore
        }
        setPendingSubscriptionId(null);
    }, []);

    const loadAllSubscriptionData = useCallback(async (signal) => {
        try {
            const [plansData, historyData] = await Promise.all([
                fetchWompiPlans(signal),
                fetchWompiHistory(signal),
            ]);

            setPlans(Array.isArray(plansData) ? plansData : []);
            setActiveSub(historyData?.active_subscription || null);
            setHistory(Array.isArray(historyData?.history) ? historyData.history : []);
            return historyData;
        } catch (err) {
            if (!signal?.aborted) {
                setError(
                    err?.response?.data?.message ||
                        err?.message ||
                        "No se pudo sincronizar la información de suscripciones desde PostgreSQL."
                );
            }
            return null;
        } finally {
            if (!signal?.aborted) {
                setLoading(false);
            }
        }
    }, []);

    /**
     * Verifica inmediatamente el estado de una suscripción en PostgreSQL.
     * Si cambia a 'active' o 'cancelled', detiene el polling, recarga GET /api/payments/history,
     * cierra el modal si estaba esperando y actualiza el banner superior y la tabla.
     */
    const checkPendingSubscriptionOnce = useCallback(
        async (subIdToVerify) => {
            const targetId = Number(subIdToVerify);
            if (!targetId || isCheckingRef.current) return null;

            isCheckingRef.current = true;
            try {
                const sub = await fetchWompiSubscriptionStatus(targetId);
                if (!sub) return null;

                if (sub.status === "active") {
                    stopPolling();
                    clearStoredPendingSub();
                    setIsModalOpen(false);
                    await loadAllSubscriptionData();
                    setVerificationBanner({
                        status: "active",
                        title: "¡Suscripción Aprobada y Activada!",
                        message: `Tu plan ${sub.plan_name || sub.plan} (#${sub.id}) fue confirmado por Wompi y ya se encuentra activo hasta el ${formatDateEs(sub.end_date)}.`,
                        subscriptionId: sub.id,
                    });
                    return sub;
                }

                if (sub.status === "cancelled" || sub.status === "expired") {
                    stopPolling();
                    clearStoredPendingSub();
                    setIsModalOpen(false);
                    await loadAllSubscriptionData();
                    setVerificationBanner({
                        status: "cancelled",
                        title: "Transacción Rechazada o Cancelada",
                        message: `El pago de la suscripción #${sub.id} (${sub.plan_name || sub.plan}) fue reportado como rechazado o anulado por Wompi.`,
                        subscriptionId: sub.id,
                    });
                    return sub;
                }

                // Continúa en 'pending'
                setVerificationBanner({
                    status: "pending",
                    title: "Esperando confirmación de pago en Wompi...",
                    message: `Monitoreando automáticamente en vivo la suscripción #${sub.id} (${sub.plan_name || sub.plan}). En cuanto completes el pago en la otra pestaña, tu plan se activará aquí sin recargar.`,
                    subscriptionId: sub.id,
                });

                return sub;
            } catch {
                return null;
            } finally {
                isCheckingRef.current = false;
            }
        },
        [clearStoredPendingSub, loadAllSubscriptionData, stopPolling]
    );

    /**
     * Inicia o reinicia el ciclo de polling automático cada 3 segundos (hasta por 3 minutos = 60 intentos)
     * para la suscripción pendiente indicada.
     */
    const startLivePolling = useCallback(
        (subscriptionId, maxAttempts = MAX_POLL_ATTEMPTS) => {
            const targetId = Number(subscriptionId);
            if (!targetId) return;

            stopPolling();
            pollAttemptRef.current = 0;
            setPendingSubscriptionId(targetId);
            setIsPolling(true);

            try {
                sessionStorage.setItem("athena_last_checkout_sub_id", String(targetId));
            } catch {
                // ignore
            }

            const runStep = async () => {
                pollAttemptRef.current += 1;
                const currentAttempt = pollAttemptRef.current;

                const sub = await checkPendingSubscriptionOnce(targetId);

                // Si ya cambió a active / cancelled, checkPendingSubscriptionOnce detuvo el polling
                if (!sub || sub.status !== "pending") {
                    return;
                }

                if (currentAttempt < maxAttempts) {
                    pollingTimerRef.current = setTimeout(runStep, POLL_INTERVAL_MS);
                } else {
                    setIsPolling(false);
                    setVerificationBanner({
                        status: "pending",
                        title: "Suscripción Pendiente de Confirmación",
                        message: `La suscripción #${targetId} sigue pendiente en PostgreSQL. Puedes pulsar 'Verificar estado ahora' si ya completaste el pago en Wompi.`,
                        subscriptionId: targetId,
                    });
                }
            };

            runStep();
        },
        [checkPendingSubscriptionOnce, stopPolling]
    );

    // Carga inicial y detección de retorno por query params o sessionStorage
    useEffect(() => {
        const controller = new AbortController();

        const init = async () => {
            const historyData = await loadAllSubscriptionData(controller.signal);

            const urlWompiTxId = searchParams.get("id");
            const urlSubId = searchParams.get("subscription_id");
            let storedSubId = null;
            try {
                storedSubId = sessionStorage.getItem("athena_last_checkout_sub_id");
            } catch {
                storedSubId = null;
            }

            const targetSubId =
                urlSubId ||
                storedSubId ||
                (urlWompiTxId && historyData?.latest_subscription?.id
                    ? String(historyData.latest_subscription.id)
                    : null);

            if (targetSubId) {
                if (urlWompiTxId || urlSubId) {
                    const nextParams = new URLSearchParams(searchParams);
                    nextParams.delete("id");
                    nextParams.delete("env");
                    nextParams.delete("subscription_id");
                    setSearchParams(nextParams, { replace: true });
                }
                startLivePolling(Number(targetSubId), MAX_POLL_ATTEMPTS);
            }
        };

        init();

        return () => {
            controller.abort();
            stopPolling();
        };
    }, []);

    // Listeners de window ("focus") y document ("visibilitychange") para consultar de inmediato
    // en cuanto el estudiante vuelve a la pestaña de ATHENA tras pagar en la pestaña de Wompi
    useEffect(() => {
        if (!pendingSubscriptionId) return undefined;

        const handleImmediateCheckOnReturn = () => {
            if (document.visibilityState === "hidden") return;
            checkPendingSubscriptionOnce(pendingSubscriptionId);
        };

        window.addEventListener("focus", handleImmediateCheckOnReturn);
        document.addEventListener("visibilitychange", handleImmediateCheckOnReturn);

        return () => {
            window.removeEventListener("focus", handleImmediateCheckOnReturn);
            document.removeEventListener(
                "visibilitychange",
                handleImmediateCheckOnReturn
            );
        };
    }, [pendingSubscriptionId, checkPendingSubscriptionOnce]);

    const handleSelectPlan = async (plan) => {
        if (!plan?.purchasable) return;

        setError("");
        setStartingPlanKey(plan.plan_key);

        try {
            const sessionData = await createWompiCheckoutSession(plan.plan_key);
            if (!sessionData || !sessionData.checkout_url) {
                throw new Error(
                    "El servidor no devolvió los datos firmados de la sesión de pago."
                );
            }

            setCheckoutSession(sessionData);
            setIsModalOpen(true);

            // Actualizar historial para mostrar la nueva suscripción pendiente y activar el monitoreo en vivo
            await loadAllSubscriptionData();
            startLivePolling(sessionData.subscription_id, MAX_POLL_ATTEMPTS);
        } catch (err) {
            const validationError = Object.values(
                err?.response?.data?.errors ?? {}
            )
                .flat()
                .find(Boolean);
            setError(
                validationError ||
                    err?.response?.data?.message ||
                    err?.message ||
                    "No se pudo iniciar la transacción con Wompi."
            );
        } finally {
            setStartingPlanKey("");
        }
    };

    const handleWebCheckoutOpened = (subscriptionId) => {
        // Inicia el monitoreo en vivo cada 3s mientras el modal muestra "Esperando confirmación de pago en Wompi..."
        // (y el enlace directo <a target="_blank"> si el navegador bloqueó el popup).
        // Al aprobarse o rechazarse, checkPendingSubscriptionOnce cierra el modal automáticamente.
        startLivePolling(subscriptionId, MAX_POLL_ATTEMPTS);
    };

    const handleWidgetFinished = (subscriptionId) => {
        setIsModalOpen(false);
        startLivePolling(subscriptionId, MAX_POLL_ATTEMPTS);
    };

    return (
        <div className="sub-page">
            {/* 1. CABECERA Y BANNER DE SUSCRIPCIÓN ACTIVA */}
            <header className="sub-header">
                <div className="sub-header__left">
                    <span className="sub-kicker">
                        <i className="fas fa-crown"></i>
                        ATHENA PREMIUM • PASARELA OFICIAL WOMPI
                    </span>
                    <h1 className="sub-title">Planes y Suscripciones</h1>
                    <p className="sub-subtitle">
                        Elige tu plan de preparación ICFES Saber 11 y paga de forma segura con Wompi Bancolombia (Nequi, PSE, Tarjetas o Botón Bancolombia).
                    </p>
                </div>

                {activeSub ? (
                    <div className="sub-active-card">
                        <div className="sub-active-card__icon">
                            <i className="fas fa-shield-halved"></i>
                        </div>
                        <div className="sub-active-card__body">
                            <div className="sub-active-card__top">
                                <span className="sub-active-card__label">Plan Actual:</span>
                                <span className="sub-active-card__pill">
                                    {activeSub.status_label || "Aprobada"}
                                </span>
                            </div>
                            <p className="sub-active-card__name">
                                {activeSub.plan_name} •{" "}
                                <span className="sub-active-card__price">
                                    {formatCop(activeSub.price_cop)}
                                </span>
                            </p>
                            <p className="sub-active-card__meta">
                                Vence: {formatDateEs(activeSub.end_date)} • Suscripción #{activeSub.id}
                            </p>
                        </div>
                    </div>
                ) : (
                    <div className="sub-active-card sub-active-card--free">
                        <div className="sub-active-card__icon">
                            <i className="fas fa-user-graduate"></i>
                        </div>
                        <div className="sub-active-card__body">
                            <div className="sub-active-card__top">
                                <span className="sub-active-card__label">Estado de Cuenta:</span>
                                <span className="sub-active-card__pill sub-active-card__pill--free">
                                    Plan Básico Gratuito
                                </span>
                            </div>
                            <p className="sub-active-card__name">Sin suscripción Premium activa</p>
                            <p className="sub-active-card__meta">
                                Adquiere cualquiera de los planes para desbloquear acceso ilimitado.
                            </p>
                        </div>
                    </div>
                )}
            </header>

            {/* 2. BANNER DE ESTADO REAL DE SUSCRIPCIÓN / POLLING EN VIVO WOMPI */}
            {verificationBanner && (
                <div
                    className={`sub-status-alert sub-status-alert--${verificationBanner.status}`}
                    role="status"
                >
                    <div className="sub-status-alert__left">
                        <i
                            className={
                                verificationBanner.status === "active"
                                    ? "fas fa-circle-check"
                                    : verificationBanner.status === "pending"
                                    ? `fas ${isPolling ? "fa-circle-notch fa-spin" : "fa-clock"}`
                                    : "fas fa-triangle-exclamation"
                            }
                        ></i>
                        <div>
                            <strong>{verificationBanner.title}</strong>
                            <div>{verificationBanner.message}</div>
                        </div>
                    </div>
                    <div className="sub-status-alert__actions">
                        {verificationBanner.subscriptionId && (
                            <button
                                type="button"
                                className="sub-status-alert__btn"
                                onClick={() =>
                                    startLivePolling(
                                        verificationBanner.subscriptionId,
                                        MAX_POLL_ATTEMPTS
                                    )
                                }
                            >
                                <i className="fas fa-arrows-rotate"></i>
                                <span>Verificar estado ahora</span>
                            </button>
                        )}
                        <button
                            type="button"
                            className="sub-status-alert__btn"
                            onClick={() => {
                                stopPolling();
                                setVerificationBanner(null);
                            }}
                        >
                            <i className="fas fa-xmark"></i>
                        </button>
                    </div>
                </div>
            )}

            {error && (
                <div className="sub-status-alert sub-status-alert--error" role="alert">
                    <div className="sub-status-alert__left">
                        <i className="fas fa-circle-exclamation"></i>
                        <span>{error}</span>
                    </div>
                    <button
                        type="button"
                        className="sub-status-alert__btn"
                        onClick={() => setError("")}
                    >
                        <i className="fas fa-xmark"></i>
                    </button>
                </div>
            )}

            {/* 3. GRILLA DE PLANES DESDE POSTGRESQL (/api/payments/plans) */}
            <section className="sub-plans-grid">
                {loading ? (
                    <div
                        className="sub-plan-card"
                        style={{ gridColumn: "1 / -1", textAlign: "center", padding: "40px" }}
                    >
                        <p style={{ color: "#94a3b8", margin: 0 }}>
                            <i
                                className="fas fa-circle-notch fa-spin"
                                style={{ marginRight: "8px" }}
                            ></i>
                            Cargando catálogo oficial de planes desde PostgreSQL...
                        </p>
                    </div>
                ) : (
                    plans.map((plan) => {
                        const isPopular =
                            plan.highlight === "popular" || plan.plan_key === "quarterly";
                        const isBest =
                            plan.highlight === "best" || plan.plan_key === "annual";
                        const isCurrentActive =
                            activeSub && activeSub.plan_key === plan.plan_key;
                        const isStartingThis = startingPlanKey === plan.plan_key;
                        const features =
                            Array.isArray(plan.features) && plan.features.length > 0
                                ? plan.features
                                : DEFAULT_FEATURES_BY_KEY[plan.plan_key] ||
                                  DEFAULT_FEATURES_BY_KEY.monthly;

                        return (
                            <article
                                key={plan.plan_key}
                                className={`sub-plan-card ${
                                    isPopular
                                        ? "sub-plan-card--popular"
                                        : isBest
                                        ? "sub-plan-card--best"
                                        : ""
                                } ${!plan.purchasable ? "sub-plan-card--disabled" : ""}`}
                            >
                                {(plan.badge || isPopular || isBest) && (
                                    <div
                                        className={`sub-plan-card__ribbon ${
                                            isBest ? "sub-plan-card__ribbon--best" : ""
                                        }`}
                                    >
                                        {plan.badge ||
                                            (isPopular ? "MÁS POPULAR" : "MEJOR VALOR")}
                                    </div>
                                )}

                                <div>
                                    <div className="sub-plan-card__top-row">
                                        <span className="sub-plan-card__period">
                                            Plan {plan.period_label || plan.plan_key}
                                        </span>
                                        <span className="sub-plan-card__duration-pill">
                                            {plan.duration_days} días
                                        </span>
                                    </div>

                                    <h2 className="sub-plan-card__title">{plan.name}</h2>
                                    <p className="sub-plan-card__desc">
                                        {plan.description ||
                                            "Preparación integral ICFES Saber 11 con simulacros y Tutor IA."}
                                    </p>

                                    <div className="sub-plan-card__price-box">
                                        <span className="sub-plan-card__price-val">
                                            {plan.purchasable
                                                ? formatCop(plan.price_cop)
                                                : "No habilitado"}
                                        </span>
                                        <div className="sub-plan-card__price-meta">
                                            <span>
                                                Vigencia de {plan.duration_days} días
                                            </span>
                                            <span>Pago único vía Wompi</span>
                                        </div>
                                    </div>

                                    <ul className="sub-plan-card__features">
                                        {features.map((feat, idx) => (
                                            <li
                                                key={idx}
                                                className="sub-plan-card__feature-item"
                                            >
                                                <i className="fas fa-check-circle"></i>
                                                <span>{feat}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>

                                <button
                                    type="button"
                                    disabled={!plan.purchasable || Boolean(startingPlanKey)}
                                    onClick={() => handleSelectPlan(plan)}
                                    className={`sub-plan-card__btn ${
                                        isPopular
                                            ? "sub-plan-card__btn--popular"
                                            : isBest
                                            ? "sub-plan-card__btn--best"
                                            : ""
                                    }`}
                                >
                                    {isStartingThis ? (
                                        <>
                                            <i className="fas fa-circle-notch fa-spin"></i>
                                            <span>Generando referencia Wompi...</span>
                                        </>
                                    ) : !plan.purchasable ? (
                                        <>
                                            <i className="fas fa-ban"></i>
                                            <span>No disponible para compra</span>
                                        </>
                                    ) : (
                                        <>
                                            <i className="fas fa-bolt"></i>
                                            <span>
                                                {isCurrentActive
                                                    ? "Renovar Plan con Wompi"
                                                    : "Adquirir Plan con Wompi"}
                                            </span>
                                        </>
                                    )}
                                </button>
                            </article>
                        );
                    })
                )}
            </section>

            {/* 4. HISTORIAL REAL DE TRANSACCIONES Y SUSCRIPCIONES DESDE POSTGRESQL */}
            <section className="sub-history-section">
                <div className="sub-history-header">
                    <div>
                        <h3 className="sub-history-title">
                            <i className="fas fa-receipt"></i>
                            Historial Real de Pagos y Suscripciones
                        </h3>
                        <p className="sub-history-subtitle">
                            Registros sincronizados en vivo desde PostgreSQL y confirmados mediante Webhooks firmados de Wompi.
                        </p>
                    </div>
                    <span className="sub-history-count">
                        {history.length} registro(s)
                    </span>
                </div>

                {history.length === 0 ? (
                    <div className="sub-empty-history">
                        <i className="fas fa-file-invoice-dollar"></i>
                        <p>
                            Aún no registras suscripciones ni intentos de pago en tu cuenta.
                        </p>
                    </div>
                ) : (
                    <div className="sub-table-wrap">
                        <table className="sub-history-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Plan</th>
                                    <th>Referencia Firmada Wompi</th>
                                    <th>Vigencia</th>
                                    <th>Monto</th>
                                    <th>Estado</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                {history.map((item) => (
                                    <tr key={item.id}>
                                        <td>
                                            <strong>#{item.id}</strong>
                                        </td>
                                        <td>
                                            <div style={{ fontWeight: 700, color: "#ffffff" }}>
                                                {item.plan_name}
                                            </div>
                                            <div style={{ fontSize: "0.75rem", color: "#64748b" }}>
                                                {item.duration_days} días
                                            </div>
                                        </td>
                                        <td>
                                            <code
                                                className="sub-ref-code"
                                                title={item.reference}
                                            >
                                                {String(item.reference).length > 32
                                                    ? `${String(item.reference).slice(0, 32)}...`
                                                    : item.reference}
                                            </code>
                                        </td>
                                        <td>
                                            <div>Inicio: {formatDateEs(item.start_date)}</div>
                                            <div style={{ fontSize: "0.75rem", color: "#64748b" }}>
                                                Fin: {formatDateEs(item.end_date)}
                                            </div>
                                        </td>
                                        <td>
                                            <strong style={{ color: "#ffffff" }}>
                                                {formatCop(item.price_cop)}
                                            </strong>
                                        </td>
                                        <td>
                                            <span
                                                className={`sub-status-pill sub-status-pill--${item.status}`}
                                            >
                                                <i
                                                    className={
                                                        item.status === "active"
                                                            ? "fas fa-check-circle"
                                                            : item.status === "pending"
                                                            ? "fas fa-clock"
                                                            : "fas fa-times-circle"
                                                    }
                                                ></i>
                                                <span>{item.status_label}</span>
                                            </span>
                                        </td>
                                        <td>
                                            <button
                                                type="button"
                                                className="sub-verify-btn"
                                                onClick={() =>
                                                    startLivePolling(item.id, 10)
                                                }
                                                title="Consultar estado actualizado en PostgreSQL"
                                            >
                                                <i className="fas fa-arrows-rotate"></i>
                                                <span>Verificar</span>
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>

            {/* MODAL UNIFICADO DE CHECKOUT WOMPI (WIDGET OFICIAL + WEB CHECKOUT EN NUEVA PESTAÑA) */}
            <WompiCheckoutModal
                checkoutSession={checkoutSession}
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                onWidgetFinished={handleWidgetFinished}
                onWebCheckoutOpened={handleWebCheckoutOpened}
                isWaitingConfirmation={
                    Boolean(pendingSubscriptionId) &&
                    Number(checkoutSession?.subscription_id) === Number(pendingSubscriptionId)
                }
            />
        </div>
    );
};

export default Subscription;
