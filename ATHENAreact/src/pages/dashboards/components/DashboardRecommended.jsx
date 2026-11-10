import { Link } from "react-router-dom";
import Librito from "../../../assets/images/Librito.png";
import useProgressStore from "../../../stores/useProgressStore";

/**
 * Subcomponente DashboardRecommended
 * Utiliza un selector atómico de Zustand para obtener el tema más débil sin prop drilling.
 */
const DashboardRecommended = () => {
    // Selector atómico: solo se suscribe al tema con menor desempeño
    const temaDebil = useProgressStore(
        (state) =>
            state.dashboard?.temas_debiles?.[0] ||
            state.dashboard?.debilidades?.[0] ||
            null
    );

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
                            "Tema por mejorar"}
                    </h2>
                    <p>
                        Hemos detectado que este tema necesita refuerzo. Practica con simulacros para
                        mejorar tu rendimiento.
                    </p>
                </>
            ) : (
                <>
                    <h2>Comienza a medir tu progreso</h2>
                    <p>
                        Realiza un simulacro para que ATHENA pueda analizar tus fortalezas y temas
                        por mejorar.
                    </p>
                </>
            )}

            <Link to="/mock/quick" className="btn-link">
                <button className="btn-cta">
                    Comenzar ahora
                    <i className="fas fa-arrow-right"></i>
                </button>
            </Link>

            <div className="mascot-book">
                <img src={Librito} alt="Libro 3D" />
            </div>
        </section>
    );
};

export default DashboardRecommended;
