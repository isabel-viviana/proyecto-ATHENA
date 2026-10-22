import { useShallow } from "zustand/react/shallow";
import useProgressStore from "../../../stores/useProgressStore";

/**
 * Subcomponente DashboardStatsOverview
 * Demuestra el uso de useShallow en el segundo store (useProgressStore):
 * Selecciona múltiples métricas calculadas sin disparar re-renderizados innecesarios.
 */
const DashboardStatsOverview = () => {
    const { totalSimulacros, porcentajeGlobal, mejorResultado } = useProgressStore(
        useShallow((state) => {
            const resumen = state.dashboard?.resumen_general || {};
            return {
                totalSimulacros: Number(resumen.total_simulacros_completados || 0),
                porcentajeGlobal: Number(resumen.porcentaje_global_aciertos || 0),
                mejorResultado: Number(resumen.mejor_resultado || 0),
            };
        })
    );

    return (
        <div className="stats-overview">
            <div className="stat-card">
                <div className="stat-icon fire">
                    <i className="fas fa-fire"></i>
                </div>
                <div className="stat-content">
                    <span className="stat-value">{totalSimulacros}</span>
                    <p className="stat-label">SIMULACROS</p>
                    <p className="stat-sub">Completados</p>
                </div>
            </div>

            <div className="stat-card">
                <div className="stat-icon star">
                    <i className="fas fa-star"></i>
                </div>
                <div className="stat-content">
                    <span className="stat-value">{porcentajeGlobal}%</span>
                    <p className="stat-label">ACIERTO GLOBAL</p>
                    <p className="stat-sub green">Mejor: {mejorResultado}%</p>
                </div>
            </div>
        </div>
    );
};

export default DashboardStatsOverview;
