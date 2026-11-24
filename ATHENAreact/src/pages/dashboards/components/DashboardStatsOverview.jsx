import { useShallow } from "zustand/react/shallow";
import useProgressStore from "../../../stores/useProgressStore";

/**
 * Subcomponente DashboardStatsOverview
 * Consume useProgressStore conectado a Question Bank para mostrar métricas reales
 * de prácticas realizadas, simulacros completados y rendimiento global.
 */
const DashboardStatsOverview = () => {
    const {
        totalPracticas,
        totalPreguntasPractica,
        totalSimulacros,
        porcentajeGlobal,
        mejorResultado,
        totalPreguntas,
    } = useProgressStore(
        useShallow((state) => {
            const resumen = state.dashboard?.resumen_general || {};
            const summary = state.dashboard?.summary || {};
            return {
                totalPracticas: Number(
                    summary.practice_sessions_count ??
                        resumen.total_practicas_realizadas ??
                        0
                ),
                totalPreguntasPractica: Number(
                    summary.practice_questions_answered ??
                        resumen.total_preguntas_practica ??
                        0
                ),
                totalSimulacros: Number(
                    summary.completed_exams_count ??
                        resumen.total_simulacros_completados ??
                        0
                ),
                porcentajeGlobal: Number(
                    summary.accuracy ??
                        resumen.porcentaje_global_aciertos ??
                        0
                ),
                mejorResultado: Number(
                    summary.best_accuracy ??
                        resumen.mejor_resultado ??
                        0
                ),
                totalPreguntas: Number(
                    summary.total_answered ??
                        resumen.total_preguntas_respondidas ??
                        0
                ),
            };
        })
    );

    return (
        <div className="stats-overview">
            <div className="stat-card">
                <div className="stat-icon" style={{ background: "rgba(56, 189, 248, 0.15)", color: "#38bdf8" }}>
                    <i className="fas fa-dumbbell"></i>
                </div>
                <div className="stat-content">
                    <span className="stat-value">{totalPracticas}</span>
                    <p className="stat-label">PRÁCTICAS</p>
                    <p className="stat-sub">
                        {totalPreguntasPractica} {totalPreguntasPractica === 1 ? "reactivo" : "reactivos"}
                    </p>
                </div>
            </div>

            <div className="stat-card">
                <div className="stat-icon fire">
                    <i className="fas fa-clipboard-check"></i>
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
                    <p className="stat-sub green">
                        {totalPreguntas > 0 ? `Mejor: ${mejorResultado}%` : "Sin intentos aún"}
                    </p>
                </div>
            </div>
        </div>
    );
};

export default DashboardStatsOverview;
