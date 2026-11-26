/**
 * Servicio Oficial de Pagos y Suscripciones Wompi para ATHENA.
 * Conectado 100% a PostgreSQL (/api/payments/*) y al Widget / Web Checkout de Wompi.
 */
import api from "../api/axios";

const WOMPI_WIDGET_SCRIPT_URL = "https://checkout.wompi.co/widget.js";

export const formatCop = (amount) => {
    const value = Number(amount);
    if (
        amount === null ||
        amount === undefined ||
        amount === "" ||
        !Number.isFinite(value)
    ) {
        return "No disponible";
    }

    return new Intl.NumberFormat("es-CO", {
        style: "currency",
        currency: "COP",
        maximumFractionDigits: 0,
    }).format(value);
};

export const formatDateEs = (dateStr) => {
    if (!dateStr) return "Pendiente de activación";
    try {
        const normalized = String(dateStr).includes("T")
            ? dateStr
            : `${dateStr}T12:00:00`;
        return new Date(normalized).toLocaleDateString("es-CO", {
            year: "numeric",
            month: "short",
            day: "2-digit",
        });
    } catch {
        return String(dateStr);
    }
};

/**
 * Consulta los planes de suscripción configurados en PostgreSQL.
 * GET /api/payments/plans
 */
export const fetchWompiPlans = async (signal) => {
    const response = await api.get("/payments/plans", { signal });
    return response.data?.data || [];
};

/**
 * Consulta la suscripción activa actual y el historial real de pagos del usuario en PostgreSQL.
 * GET /api/payments/history
 */
export const fetchWompiHistory = async (signal) => {
    const response = await api.get("/payments/history", { signal });
    return (
        response.data?.data || {
            active_subscription: null,
            latest_subscription: null,
            history: [],
        }
    );
};

/**
 * Consulta el estado actualizado de una suscripción específica en PostgreSQL.
 * GET /api/payments/subscriptions/{subscriptionId}
 */
export const fetchWompiSubscriptionStatus = async (subscriptionId) => {
    const response = await api.get(`/payments/subscriptions/${subscriptionId}`);
    return response.data?.data || null;
};

/**
 * Crea una suscripción en estado 'pending' en PostgreSQL y obtiene los parámetros firmados
 * para el Widget Oficial de Wompi y la URL de redirección Web Checkout.
 * POST /api/payments/checkout
 */
export const createWompiCheckoutSession = async (planKey) => {
    const response = await api.post("/payments/checkout", {
        plan_key: planKey,
    });
    return response.data?.data;
};

/**
 * Carga robusta de https://checkout.wompi.co/widget.js con detección de window.WidgetCheckout,
 * manejo de onerror y timeout de 8 segundos para evitar spinners infinitos si un bloqueador lo intercepta.
 */
let widgetScriptPromise = null;

export const loadWompiWidgetScript = (timeoutMs = 8000) => {
    if (typeof window !== "undefined" && typeof window.WidgetCheckout === "function") {
        return Promise.resolve(window.WidgetCheckout);
    }

    if (widgetScriptPromise) {
        return widgetScriptPromise;
    }

    widgetScriptPromise = new Promise((resolve, reject) => {
        let settled = false;

        const finishResolve = () => {
            if (settled) return;
            if (typeof window.WidgetCheckout === "function") {
                settled = true;
                clearTimeout(timer);
                resolve(window.WidgetCheckout);
            } else {
                settled = true;
                clearTimeout(timer);
                widgetScriptPromise = null;
                reject(
                    new Error(
                        "El script de Wompi se descargó pero WidgetCheckout no se inicializó. Usa el botón 'Continuar en página de Wompi'."
                    )
                );
            }
        };

        const finishReject = (message) => {
            if (settled) return;
            settled = true;
            clearTimeout(timer);
            widgetScriptPromise = null;
            reject(new Error(message));
        };

        const timer = setTimeout(() => {
            finishReject(
                "Tiempo de espera agotado (8s) al cargar widget.js de Wompi. Es posible que un bloqueador de anuncios esté activo; usa 'Continuar en página de Wompi'."
            );
        }, timeoutMs);

        const existingScript = document.querySelector(
            `script[src="${WOMPI_WIDGET_SCRIPT_URL}"]`
        );

        if (existingScript) {
            if (typeof window.WidgetCheckout === "function") {
                finishResolve();
                return;
            }
            existingScript.addEventListener("load", finishResolve, { once: true });
            existingScript.addEventListener(
                "error",
                () =>
                    finishReject(
                        "No fue posible cargar widget.js de Wompi. Usa la opción 'Continuar en página de Wompi'."
                    ),
                { once: true }
            );
            return;
        }

        const script = document.createElement("script");
        script.type = "text/javascript";
        script.src = WOMPI_WIDGET_SCRIPT_URL;
        script.async = true;

        script.onload = finishResolve;
        script.onerror = () => {
            try {
                script.remove();
            } catch {
                // ignore
            }
            finishReject(
                "No se pudo conectar con checkout.wompi.co/widget.js. Puedes completar tu pago con el botón 'Continuar en página de Wompi'."
            );
        };

        document.head.appendChild(script);
    });

    return widgetScriptPromise;
};

/**
 * Inicializa y abre el Widget Oficial de Wompi usando exactamente los datos firmados por Laravel.
 */
export const openOfficialWompiWidget = async (checkoutSession, onResult) => {
    const WidgetCheckout = await loadWompiWidgetScript(8000);

    const redirectUrl =
        checkoutSession.redirect_url ||
        `${window.location.origin}/suscripciones?subscription_id=${checkoutSession.subscription_id}`;

    const widgetConfig = {
        currency: checkoutSession.currency || "COP",
        amountInCents: Number(checkoutSession.amount_in_cents),
        reference: checkoutSession.reference,
        publicKey: checkoutSession.public_key,
        signature: {
            integrity: checkoutSession.integrity_signature,
        },
        redirectUrl,
    };

    if (checkoutSession.customer_data?.email) {
        widgetConfig.customerData = {
            email: checkoutSession.customer_data.email,
            fullName:
                checkoutSession.customer_data.full_name || "Estudiante ATHENA",
        };
        if (checkoutSession.customer_data.phone_number) {
            widgetConfig.customerData.phoneNumber = String(
                checkoutSession.customer_data.phone_number
            );
            widgetConfig.customerData.phoneNumberPrefix = "+57";
        }
    }

    const checkout = new WidgetCheckout(widgetConfig);

    checkout.open((result) => {
        if (typeof onResult === "function") {
            onResult(result?.transaction || null);
        }
    });
};
