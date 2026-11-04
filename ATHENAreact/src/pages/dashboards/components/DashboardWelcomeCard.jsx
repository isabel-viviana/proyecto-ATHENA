import { Link } from "react-router-dom";


const DashboardWelcomeCard = () => {
    return (
        <section className="mb-8 p-6 sm:p-8 rounded-2xl bg-[#0a1128] border border-slate-800 shadow-xl shadow-black/30 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6 transition-all duration-300 hover:border-slate-700">
            <div className="space-y-2">
                <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 text-xs font-medium tracking-wide uppercase">
                    <i className="fas fa-graduation-cap text-xs"></i>
                    <span>Prácticas ICFES</span>
                </div>
                <h2 className="text-2xl sm:text-3xl font-bold text-white tracking-tight">
                    Bienvenido a ATHENA
                </h2>
                <p className="text-sm sm:text-base text-slate-300 max-w-xl leading-relaxed">
                    Continúa practicando para prepararte para las pruebas ICFES.
                </p>
            </div>

            <Link
                to="/practices"
                className="inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white font-semibold text-sm sm:text-base shadow-lg shadow-indigo-600/30 hover:shadow-indigo-500/40 transition-all duration-200 cursor-pointer shrink-0"
            >
                <span>Comenzar práctica</span>
                <i className="fas fa-arrow-right text-xs"></i>
            </Link>
        </section>
    );
};

export default DashboardWelcomeCard;
