import { Link } from "react-router-dom";
import { useShallow } from "zustand/react/shallow";
import Librito from "../../../assets/images/Librito.png";
import useProgressStore from "../../../stores/useProgressStore";

/**
 * Subcomponente DashboardRecommended
 * Presenta recomendaciones académicas basadas en el desempeño real del estudiante en Question Bank.
 */
const DashboardRecommended = () => {
    const { temaDebil, areaPorReforzar, totalRespondidas, porcentajeGlobal } = useProgressStore(
        useShallow((state) => ({
            temaDebil:
                state.dashboard?.weak_topics?.[0] ||
                state.dashboard?.temas_debiles?.[0] ||
                null,
            areaPorReforzar:
                state.dashboard?.academic_analysis?.needs_practice_area || null,
            totalRespondidas: Number(
                state.dashboard?.summary?.total_answered ??
                    state.dashboard?.resumen_general?.total_preguntas_respondidas ??
                    0
            ),
            porcentajeGlobal: Number(
                state.dashboard?.summary?.accuracy ??
                    state.dashboard?.resumen_general?.porcentaje_global_aciertos ??
                    0
            ),
        }))
    );

    const targetSubjectId = temaDebil?.subject_id || areaPorReforzar?.subject_id || 1;
    const targetSubjectName =
        temaDebil?.subject_name || areaPorReforzar?.name || "Lectura Crítica";
    const practiceUrl = `/practices-full?subject_id=${targetSubjectId}&subject_name=${encodeURIComponent(
        targetSubjectName
    )}`;

    return (
        <section className="card recommended-card">
            <span className="tag">
                <i className="fas fa-bolt"></i>
                RECOMENDADO HOY
            </span>

            {temaDebil ? (
                <>
                    <h2>
                        Refuerza:{" "}
                        {temaDebil.topic_name ||
                            temaDebil.name ||
                            targetSubjectName}
                    </h2>
                    <p>
                        En <strong>{targetSubjectName}</strong> registras un{" "}
                        <strong>{temaDebil.accuracy ?? 0}% de acierto</strong> (
                        {temaDebil.error_rate ?? Math.round(100 - (temaDebil.accuracy || 0))}% de error en{" "}
                        {temaDebil.total_answered || 1} reactivos). Practica este tema o realiza un mini
                        simulacro para elevar tu dominio.
                    </p>
                </>
            ) : areaPorReforzar ? (
                <>
                    <h2>Refuerza: {areaPorReforzar.name}</h2>
                    <p>
                        Hemos detectado un{" "}
                        <strong>{areaPorReforzar.accuracy}% de efectividad</strong> en{" "}
                        {areaPorReforzar.name}. Entrena con preguntas oficiales para superar el 70% de
                        dominio.
                    </p>
                </>
            ) : totalRespondidas > 0 ? (
                <>
                    <h2>¡Buen ritmo! ({porcentajeGlobal}% de efectividad)</h2>
                    <p>
                        Has resuelto {totalRespondidas}{" "}
                        {totalRespondidas === 1 ? "pregunta oficial" : "preguntas oficiales"} sin temas
                        críticos pendientes. Pon a prueba tu agilidad con un Mini Simulacro Rápido.
                    </p>
                </>
            ) : (
                <>
                    <h2>Comienza a medir tu progreso</h2>
                    <p>
                        Realiza un Mini Simulacro Rápido o una sesión de práctica para que ATHENA analice
                        tus fortalezas y detecte temas por reforzar con datos reales.
                    </p>
                </>
            )}

            <div style={{ display: "flex", gap: "12px", flexWrap: "wrap", position: "relative", zIndex: 2 }}>
                <Link to="/mock/quick" className="btn-link">
                    <button type="button" className="btn-cta">
                        Mini Simulacro Rápido
                        <i className="fas fa-arrow-right"></i>
                    </button>
                </Link>

                {totalRespondidas > 0 ? (
                    <Link to={temaDebil || areaPorReforzar ? practiceUrl : "/statistics"} className="btn-link">
                        <button
                            type="button"
                            className="btn-cta"
                            style={{
                                background: "rgba(30, 41, 59, 0.85)",
                                border: "1px solid rgba(96, 165, 250, 0.4)",
                                color: "#93c5fd",
                            }}
                        >
                            {temaDebil || areaPorReforzar ? "Practicar Área" : "Ver Estadísticas"}
                        </button>
                    </Link>
                ) : (
                    <Link to="/practices" className="btn-link">
                        <button
                            type="button"
                            className="btn-cta"
                            style={{
                                background: "rgba(30, 41, 59, 0.85)",
                                border: "1px solid rgba(96, 165, 250, 0.4)",
                                color: "#93c5fd",
                            }}
                        >
                            Ir a Prácticas
                        </button>
                    </Link>
                )}
            </div>

            <div className="mascot-book">
                <img src={Librito} alt="Libro 3D" />
            </div>
        </section>
    );
};

export default DashboardRecommended;
