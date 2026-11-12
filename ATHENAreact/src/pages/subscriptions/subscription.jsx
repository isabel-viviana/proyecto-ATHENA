import { useState, useEffect } from "react";
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
    const [selectedPlan, setSelectedPlan] = useState(null);
    const [isWompiOpen, setIsWompiOpen] = useState(false);

    useEffect(() => {
        const refresh = () => {
            setActiveSub(getActiveSubscription());
            setHistory(getPaymentHistory());
        };
        window.addEventListener("athena_subscription_updated", refresh);
        return () => window.removeEventListener("athena_subscription_updated", refresh);
    }, []);

    const handleAcquirePlan = (plan) => {
        const reference = `ATHENA-${plan.slug.toUpperCase()}-${Date.now()}`;
        setSelectedPlan({
            ...plan,
            reference,
        });
        setIsWompiOpen(true);
    };

    return (
        <div className="min-h-screen w-full text-[#f1f5f9] pb-[48px]">
            {/* ENCABEZADO */}
            <header className="mb-[28px] flex flex-col md:flex-row md:items-center md:justify-between gap-[16px]">
                <div>
                    <span className="inline-flex items-center gap-[8px] px-[12px] py-[4px] rounded-full text-[12px] font-semibold bg-[#6366f1]/15 text-[#a5b4fc] border border-[#6366f1]/30 mb-[10px]">
                        <i className="fas fa-crown text-[#fbbf24]"></i>
                        ATHENA PREMIUM • PASARELA WOMPI
                    </span>
                    <h1 className="text-[28px] md:text-[32px] font-extrabold text-white tracking-tight">
                        Planes y Suscripciones
                    </h1>
                    <p className="text-[14px] text-[#94a3b8] mt-[4px]">
                        Elige tu plan de preparación ICFES Saber 11 y paga de forma segura con Nequi o Wompi Bancolombia.
                    </p>
                </div>

                {/* RESUMEN DE SUSCRIPCIÓN ACTIVA */}
                {activeSub && (
                    <div className="bg-[#0a1128] border border-[#1a2238] rounded-[16px] px-[20px] py-[14px] flex items-center gap-[16px]">
                        <div className="w-[42px] h-[42px] rounded-[12px] bg-[#34d399]/15 border border-[#34d399]/30 flex items-center justify-center text-[#34d399] text-[18px]">
                            <i className="fas fa-shield-check"></i>
                        </div>
                        <div>
                            <div className="flex items-center gap-[8px]">
                                <span className="text-[12px] uppercase tracking-wider text-[#94a3b8] font-semibold">
                                    Plan Actual:
                                </span>
                                <span className="px-[8px] py-[2px] rounded-full text-[11px] font-bold bg-[#34d399]/15 text-[#34d399]">
                                    Activo
                                </span>
                            </div>
                            <p className="text-[15px] font-bold text-white mt-[2px]">
                                {activeSub.planName} • <span className="text-[#a5b4fc]">{activeSub.formattedPrice}</span>
                            </p>
                            <p className="text-[12px] text-[#64748b]">
                                Vence: {activeSub.formattedEndDate} ({activeSub.paymentMethod})
                            </p>
                        </div>
                    </div>
                )}
            </header>

            {/* GRILLA DE 3 PLANES (MENSUAL, TRIMESTRAL, ANUAL) */}
            <section className="grid grid-cols-1 lg:grid-cols-3 gap-[24px] mb-[36px]">
                {SUBSCRIPTION_PLANS.map((plan) => {
                    const isCurrentPlan = activeSub?.planId === plan.id;
                    const isPopular = Boolean(plan.popular);
                    const isBestValue = Boolean(plan.bestValue);

                    return (
                        <article
                            key={plan.id}
                            className={`relative flex flex-col justify-between rounded-[20px] p-[28px] transition-all duration-200 bg-[#0a1128] border ${
                                isPopular
                                    ? "border-[#6366f1] shadow-[0_0_30px_rgba(99,102,241,0.18)]"
                                    : isBestValue
                                    ? "border-[#f59e0b]/60 shadow-[0_0_25px_rgba(245,158,11,0.12)]"
                                    : "border-[#1a2238] hover:border-[#334155]"
                            }`}
                        >
                            {/* BADGE SUPERIOR */}
                            {plan.badge && (
                                <div
                                    className={`absolute -top-[13px] right-[24px] px-[14px] py-[4px] rounded-full text-[11px] font-extrabold tracking-wide uppercase shadow-md ${
                                        isPopular
                                            ? "bg-[#6366f1] text-white"
                                            : "bg-gradient-to-r from-[#f59e0b] to-[#d97706] text-[#080c16]"
                                    }`}
                                >
                                    {plan.badge}
                                </div>
                            )}

                            <div>
                                {/* CABECERA DEL PLAN */}
                                <div className="flex items-center justify-between mb-[12px]">
                                    <span className="text-[13px] font-bold uppercase tracking-wider text-[#94a3b8]">
                                        Plan {plan.period}
                                    </span>
                                    {plan.savings && (
                                        <span className="px-[10px] py-[3px] rounded-full text-[11px] font-semibold bg-[#34d399]/15 text-[#34d399] border border-[#34d399]/30">
                                            {plan.savings}
                                        </span>
                                    )}
                                </div>

                                <h2 className="text-[22px] font-extrabold text-white mb-[8px]">
                                    {plan.name}
                                </h2>

                                <p className="text-[13px] text-[#94a3b8] leading-relaxed mb-[20px]">
                                    {plan.description}
                                </p>

                                {/* PRECIO */}
                                <div className="bg-[#0d1734] border border-[#1a2238] rounded-[14px] p-[16px] mb-[22px]">
                                    <div className="flex items-baseline gap-[6px]">
                                        <span className="text-[30px] font-black text-white">
                                            {plan.formattedPrice}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between mt-[4px] text-[12px] text-[#94a3b8]">
                                        <span>{plan.billingText}</span>
                                        <span className="text-[#a5b4fc] font-semibold">
                                            {plan.monthlyEquivalent}
                                        </span>
                                    </div>
                                </div>

                                {/* LISTA DE BENEFICIOS */}
                                <ul className="space-y-[10px] mb-[28px]">
                                    {plan.features.map((feature, idx) => (
                                        <li
                                            key={idx}
                                            className="flex items-start gap-[10px] text-[13px] text-[#cbd5e1]"
                                        >
                                            <i className="fas fa-check-circle text-[#34d399] mt-[3px] shrink-0"></i>
                                            <span>{feature}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            {/* BOTÓN ADQUIRIR PLAN -> ABRE WOMPI CHECKOUT MODAL */}
                            <button
                                type="button"
                                onClick={() => handleAcquirePlan(plan)}
                                className={`w-full py-[13px] px-[20px] rounded-[12px] font-bold text-[14px] flex items-center justify-center gap-[10px] transition-all cursor-pointer ${
                                    isPopular
                                        ? "bg-[#6366f1] hover:bg-[#4f46e5] text-white shadow-lg shadow-[#6366f1]/25"
                                        : isBestValue
                                        ? "bg-gradient-to-r from-[#f59e0b] to-[#d97706] hover:opacity-95 text-[#080c16]"
                                        : "bg-[#1e293b] hover:bg-[#334155] text-white border border-[#334155]"
                                }`}
                            >
                                <i className="fas fa-bolt"></i>
                                <span>
                                    {isCurrentPlan ? "Renovar Plan con Wompi" : "Adquirir Plan"}
                                </span>
                            </button>
                        </article>
                    );
                })}
            </section>

            {/* HISTORIAL DE TRANSACCIONES WOMPI */}
            <section className="bg-[#0a1128] border border-[#1a2238] rounded-[20px] p-[24px]">
                <div className="flex items-center justify-between mb-[20px]">
                    <div>
                        <h3 className="text-[18px] font-bold text-white">
                            Historial de Pagos y Transacciones
                        </h3>
                        <p className="text-[13px] text-[#94a3b8]">
                            Comprobantes registrados a través de Wompi Sandbox / Nequi
                        </p>
                    </div>
                    <span className="px-[12px] py-[5px] rounded-full text-[12px] font-semibold bg-[#0d1734] text-[#94a3b8] border border-[#1a2238]">
                        {history.length} transacciones
                    </span>
                </div>

                <div className="space-y-[12px]">
                    {history.map((item) => (
                        <div
                            key={item.id}
                            className="flex flex-col sm:flex-row sm:items-center justify-between gap-[12px] p-[16px] rounded-[14px] bg-[#0d1734] border border-[#1a2238]"
                        >
                            <div className="flex items-center gap-[14px]">
                                <div className="w-[40px] h-[40px] rounded-[10px] bg-[#6366f1]/15 text-[#a5b4fc] flex items-center justify-center text-[16px] shrink-0">
                                    <i className="fas fa-receipt"></i>
                                </div>
                                <div>
                                    <h4 className="text-[14px] font-bold text-white">
                                        {item.planName} — <span className="text-[#94a3b8] font-normal">{item.date}</span>
                                    </h4>
                                    <p className="text-[12px] text-[#64748b] mt-[2px]">
                                        {item.method} • Ref: <code className="text-[#a5b4fc]">{item.reference}</code>
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-center justify-between sm:justify-end gap-[14px]">
                                <span className="text-[15px] font-extrabold text-white">
                                    {item.formattedAmount}
                                </span>
                                <span className="px-[10px] py-[4px] rounded-full text-[11px] font-bold bg-[#34d399]/15 text-[#34d399] border border-[#34d399]/30">
                                    {item.status}
                                </span>
                            </div>
                        </div>
                    ))}
                </div>
            </section>

            {/* MODAL DE CHECKOUT WOMPI / NEQUI */}
            <WompiCheckoutModal
                plan={selectedPlan}
                user={user}
                isOpen={isWompiOpen}
                onClose={() => setIsWompiOpen(false)}
                onSuccess={(sub) => setActiveSub(sub)}
            />
        </div>
    );
};

export default Subscription;
