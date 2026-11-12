/**
 * Wompi & Nequi Sandbox Integration Service for ATHENA
 * Handles subscription plans, Wompi Checkout Widget, and Nequi Sandbox simulation.
 */
import api from "../api/axios";

export const SUBSCRIPTION_PLANS = [
    {
        id: "premium_monthly",
        name: "Premium Mensual",
        slug: "mensual",
        period: "Mensual",
        duration_days: 30,
        price: 29900,
        formattedPrice: "$29.900 COP",
        billingText: "Facturación mensual",
        monthlyEquivalent: "$29.900 COP / mes",
        savings: null,
        badge: null,
        popular: false,
        color: "blue",
        description: "Ideal para iniciar tu preparación paso a paso con acceso completo a las herramientas esenciales.",
        features: [
            "Simulacros ilimitados Saber 11 en todas las áreas",
            "Tutor IA ATHENA 24/7 con explicaciones paso a paso",
            "Métricas detalladas por área y componentes",
            "Banco de preguntas contextualizadas ICFES",
            "Acceso completo desde cualquier dispositivo",
            "Sin contratos ni permanencia, cancela cuando quieras",
        ],
    },
    {
        id: "premium_quarterly",
        name: "Premium Trimestral",
        slug: "trimestral",
        period: "Trimestral",
        duration_days: 90,
        price: 79900,
        formattedPrice: "$79.900 COP",
        billingText: "Facturación cada 3 meses",
        monthlyEquivalent: "$26.633 COP / mes",
        savings: "Ahorra 11%",
        badge: "⭐ MÁS POPULAR",
        popular: true,
        color: "purple",
        description: "La duración ideal recomendada por docentes para un ciclo completo de entrenamiento y consolidación.",
        features: [
            "Todo lo incluido en el Plan Mensual",
            "3 meses de entrenamiento intensivo ininterrumpido",
            "Banco VIP con preguntas ICFES 2024-2026 actualizadas",
            "Simulacros cronometrados tipo examen real con cálculo de percentil",
            "Insignia exclusiva de Estudiante Destacado en tu perfil",
            "Guías descargables de técnicas de descarte de opciones",
        ],
    },
    {
        id: "premium_annual",
        name: "Premium Anual",
        slug: "anual",
        period: "Anual",
        duration_days: 365,
        price: 219900,
        formattedPrice: "$219.900 COP",
        billingText: "Facturación anual única",
        monthlyEquivalent: "$18.325 COP / mes",
        savings: "Ahorra 38% - Mejor Valor",
        badge: "👑 MEJOR VALOR",
        popular: false,
        bestValue: true,
        color: "gold",
        description: "Preparación total y asegurada durante 1 año completo hasta el día definitivo de tu prueba ICFES.",
        features: [
            "Todo lo incluido en el Plan Trimestral",
            "Acceso total ilimitado durante 365 días",
            "Descarga de simulacros completos en PDF con clave de respuestas",
            "Análisis predictivo de puntaje global ICFES con Inteligencia Artificial",
            "Insignia dorada 'ATHENA Legend' en tu perfil",
            "Atención y soporte prioritario de tutores especializados",
            "Garantía de actualización continua de contenidos",
        ],
    },
];

export const WOMPI_CONFIG = {
    // Llaves reales Sandbox proporcionadas por el usuario
    publicKey: import.meta.env?.VITE_WOMPI_PUBLIC_KEY || "pub_test_DKop0lp8lllfzuVKFcyHJrh2LRsXTMSn",
    integritySecret: import.meta.env?.VITE_WOMPI_INTEGRITY_SECRET || "test_integrity_NNm49HyoTHa3rVxubfhukhv29uM8SrTM",
    currency: "COP",
    sandbox: true,
    widgetUrl: "https://checkout.wompi.co/widget.js",
    testNumbers: {
        nequiApproved: "3991111111", // Simula pago Aprobado
        nequiDeclined: "3992222222", // Simula pago Rechazado por fondos/usuario
        nequiError: "3993333333",    // Simula fallo en pasarela
    },
};

const STORAGE_KEY_SUB = "athena_active_subscription";
const STORAGE_KEY_HISTORY = "athena_payment_history";

/**
 * Obtiene la suscripción activa del usuario
 */
export const getActiveSubscription = () => {
    try {
        const stored = localStorage.getItem(STORAGE_KEY_SUB);
        if (stored) {
            return JSON.parse(stored);
        }
    } catch (e) {
        console.error("Error reading subscription from storage", e);
    }

    // Suscripción por defecto activa (Premium Mensual)
    const defaultEnd = new Date(Date.now() + 25 * 24 * 60 * 60 * 1000);
    const defaultSub = {
        id: "sub_initial_01",
        planId: "premium_monthly",
        planName: "Premium Mensual",
        period: "Mensual",
        price: 29900,
        formattedPrice: "$29.900 COP",
        currency: "COP",
        status: "active",
        startDate: new Date().toISOString(),
        endDate: defaultEnd.toISOString(),
        formattedEndDate: defaultEnd.toLocaleDateString("es-CO", {
            day: "numeric",
            month: "long",
            year: "numeric",
        }),
        paymentMethod: "Nequi (Wompi Sandbox)",
        transactionId: "wmp_tx_sandbox_init_4242",
        reference: "ATH-WOMPI-INIT-001",
        autoRenew: true,
    };

    try {
        localStorage.setItem(STORAGE_KEY_SUB, JSON.stringify(defaultSub));
    } catch {}

    return defaultSub;
};

/**
 * Guarda una nueva suscripción tras un pago aprobado con Wompi
 */
export const saveActiveSubscription = (plan, transactionDetails = {}) => {
    const startDate = new Date();
    const endDate = new Date(startDate.getTime() + plan.duration_days * 24 * 60 * 60 * 1000);

    const subscription = {
        id: `sub_${Date.now()}`,
        planId: plan.id,
        planName: plan.name,
        period: plan.period,
        price: plan.price,
        formattedPrice: plan.formattedPrice,
        currency: "COP",
        status: "active",
        startDate: startDate.toISOString(),
        endDate: endDate.toISOString(),
        formattedEndDate: endDate.toLocaleDateString("es-CO", {
            day: "numeric",
            month: "long",
            year: "numeric",
        }),
        paymentMethod: transactionDetails.paymentMethod || "Nequi (Wompi Sandbox)",
        transactionId: transactionDetails.id || `wmp_tx_${Date.now()}`,
        reference: transactionDetails.reference || `ATH-WOMPI-${Date.now()}`,
        phoneNumber: transactionDetails.phoneNumber || "",
        autoRenew: true,
        updatedAt: new Date().toISOString(),
    };

    localStorage.setItem(STORAGE_KEY_SUB, JSON.stringify(subscription));

    // Agregar registro al historial de transacciones
    addPaymentToHistory({
        id: subscription.transactionId,
        reference: subscription.reference,
        planName: plan.name,
        amount: plan.price,
        formattedAmount: plan.formattedPrice,
        currency: "COP",
        method: subscription.paymentMethod,
        phoneNumber: transactionDetails.phoneNumber || "3991111111",
        status: "Aprobada",
        date: startDate.toLocaleDateString("es-CO", {
            day: "numeric",
            month: "long",
            year: "numeric",
        }),
        timestamp: startDate.toISOString(),
    });

    // Intentar sincronizar con backend Laravel si está disponible
    try {
        api.post("/profile/subscription", subscription).catch(() => {});
    } catch {}

    // Notificar a toda la interfaz del cambio de plan
    window.dispatchEvent(
        new CustomEvent("athena_subscription_updated", {
            detail: subscription,
        })
    );

    return subscription;
};

/**
 * Historial de pagos
 */
export const getPaymentHistory = () => {
    try {
        const stored = localStorage.getItem(STORAGE_KEY_HISTORY);
        if (stored) {
            return JSON.parse(stored);
        }
    } catch (e) {
        console.error("Error reading payment history", e);
    }

    const defaultHistory = [
        {
            id: "wmp_tx_sandbox_001",
            reference: "ATH-WOMPI-982140",
            planName: "Premium Mensual",
            amount: 29900,
            formattedAmount: "$29.900 COP",
            currency: "COP",
            method: "Nequi (Sandbox: 3991111111)",
            phoneNumber: "3991111111",
            status: "Aprobada",
            date: "15 de Octubre, 2026",
            timestamp: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString(),
        },
        {
            id: "wmp_tx_sandbox_000",
            reference: "ATH-WOMPI-931102",
            planName: "Premium Mensual",
            amount: 29900,
            formattedAmount: "$29.900 COP",
            currency: "COP",
            method: "Nequi (Sandbox: 3991111111)",
            phoneNumber: "3991111111",
            status: "Aprobada",
            date: "15 de Septiembre, 2026",
            timestamp: new Date(Date.now() - 60 * 24 * 60 * 60 * 1000).toISOString(),
        },
    ];

    try {
        localStorage.setItem(STORAGE_KEY_HISTORY, JSON.stringify(defaultHistory));
    } catch {}

    return defaultHistory;
};

export const addPaymentToHistory = (item) => {
    const history = getPaymentHistory();
    const updated = [item, ...history];
    localStorage.setItem(STORAGE_KEY_HISTORY, JSON.stringify(updated));
    return updated;
};

/**
 * Carga dinámica del script del widget oficial de Wompi
 */
let wompiScriptPromise = null;
export const loadWompiScript = () => {
    if (window.WidgetCheckout) {
        return Promise.resolve(window.WidgetCheckout);
    }

    if (wompiScriptPromise) {
        return wompiScriptPromise;
    }

    wompiScriptPromise = new Promise((resolve, reject) => {
        // Verificar si ya existe en el DOM
        const existingScript = document.querySelector(`script[src="${WOMPI_CONFIG.widgetUrl}"]`);
        if (existingScript) {
            existingScript.addEventListener("load", () => resolve(window.WidgetCheckout));
            existingScript.addEventListener("error", reject);
            return;
        }

        const script = document.createElement("script");
        script.type = "text/javascript";
        script.src = WOMPI_CONFIG.widgetUrl;
        script.async = true;

        script.onload = () => {
            resolve(window.WidgetCheckout);
        };
        script.onerror = () => {
            reject(new Error("No se pudo cargar el script del Checkout de Wompi"));
        };

        document.head.appendChild(script);
    });

    return wompiScriptPromise;
};

/**
 * Genera el hash de firma de integridad SHA-256 requerido por Wompi Bancolombia
 * Fórmula oficial: SHA256(reference + amountInCents + currency + integritySecret)
 */
export const generateWompiIntegritySignature = async (
    reference,
    amountInCents,
    currency,
    integritySecret
) => {
    try {
        const textToSign = `${reference}${amountInCents}${currency}${integritySecret}`;
        const encoder = new TextEncoder();
        const data = encoder.encode(textToSign);
        const hashBuffer = await crypto.subtle.digest("SHA-256", data);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        return hashArray.map((b) => b.toString(16).padStart(2, "0")).join("");
    } catch (e) {
        console.warn("Could not calculate subtle crypto SHA-256 signature:", e);
        return "";
    }
};

/**
 * Abre el Checkout Widget oficial de Wompi con firma de integridad Sandbox
 */
export const openWompiCheckoutWidget = async ({ plan, user, onApproved, onDeclined, onError, onClose }) => {
    try {
        await loadWompiScript();

        if (!window.WidgetCheckout) {
            throw new Error("El Widget de Wompi no está disponible en este momento.");
        }

        const reference = `ATHENA-${plan.slug.toUpperCase()}-${Date.now()}`;
        const amountInCents = plan.price * 100;

        // Calcular firma de integridad si existe secreto de integridad configurado
        let integritySignature = "";
        if (WOMPI_CONFIG.integritySecret) {
            integritySignature = await generateWompiIntegritySignature(
                reference,
                amountInCents,
                WOMPI_CONFIG.currency,
                WOMPI_CONFIG.integritySecret
            );
        }

        const checkoutOptions = {
            currency: WOMPI_CONFIG.currency,
            amountInCents: amountInCents,
            reference: reference,
            publicKey: WOMPI_CONFIG.publicKey,
            redirectUrl: `${window.location.origin}/profile`,
            customerData: {
                email: user?.email || "estudiante@athena.edu.co",
                fullName: user?.full_name || user?.name || "Estudiante ATHENA",
                phoneNumber: user?.phone || "3991111111",
                phoneNumberPrefix: "+57",
            },
        };

        if (integritySignature) {
            checkoutOptions.signature = {
                integrity: integritySignature,
            };
        }

        const checkout = new window.WidgetCheckout(checkoutOptions);

        checkout.open((result) => {
            const transaction = result?.transaction;
            if (!transaction) {
                if (onClose) onClose();
                return;
            }

            if (transaction.status === "APPROVED") {
                const sub = saveActiveSubscription(plan, {
                    id: transaction.id,
                    reference: transaction.reference,
                    paymentMethod: `Wompi (${transaction.payment_method_type || "Nequi"})`,
                    phoneNumber: user?.phone || "3991111111",
                });
                if (onApproved) onApproved(sub, transaction);
            } else if (transaction.status === "DECLINED") {
                if (onDeclined) onDeclined(transaction);
            } else if (transaction.status === "ERROR") {
                if (onError) onError(transaction);
            } else {
                if (onClose) onClose(transaction);
            }
        });
    } catch (err) {
        console.warn("Wompi widget checkout could not open directly:", err);
        throw err;
    }
};

/**
 * Simulación directa e interactiva de Wompi Nequi Sandbox
 * Permite probar de manera guiada los números de prueba oficiales de Wompi
 */
export const simulateNequiSandbox = async ({ plan, phoneNumber, user }) => {
    const cleanPhone = (phoneNumber || "").replace(/\s+/g, "");

    // Simular latencia de red y confirmación PUSH en Nequi
    await new Promise((resolve) => setTimeout(resolve, 2200));

    // Reglas oficiales de Wompi Sandbox para Nequi
    if (cleanPhone === WOMPI_CONFIG.testNumbers.nequiDeclined) {
        return {
            status: "DECLINED",
            error: "Transacción rechazada por el usuario o fondos insuficientes en Nequi (Sandbox).",
            code: "USER_DECLINED_OR_INSUFFICIENT_FUNDS",
            phoneNumber: cleanPhone,
        };
    }

    if (cleanPhone === WOMPI_CONFIG.testNumbers.nequiError) {
        return {
            status: "ERROR",
            error: "Error técnico temporal de comunicación con el servicio de Nequi Sandbox.",
            code: "GATEWAY_TIMEOUT_OR_INTERNAL_ERROR",
            phoneNumber: cleanPhone,
        };
    }

    // Por defecto o con 3991111111: Aprobado
    const txId = `wmp_nequi_${Math.random().toString(36).substring(2, 9)}_${Date.now()}`;
    const reference = `ATH-NQ-${plan.slug.toUpperCase()}-${Date.now().toString().slice(-6)}`;

    const transactionDetails = {
        id: txId,
        reference: reference,
        status: "APPROVED",
        paymentMethod: `Nequi Sandbox (${cleanPhone})`,
        phoneNumber: cleanPhone,
        amount: plan.price,
        currency: "COP",
        userEmail: user?.email || "estudiante@athena.edu.co",
        userName: user?.full_name || user?.name || "Estudiante ATHENA",
        createdAt: new Date().toISOString(),
    };

    const savedSub = saveActiveSubscription(plan, transactionDetails);

    return {
        status: "APPROVED",
        transaction: transactionDetails,
        subscription: savedSub,
    };
};
