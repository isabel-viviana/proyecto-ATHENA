import "./mock_exam.css";
import { Link } from "react-router-dom";
import Zorrito from "../../assets/images/Zorrito2D.png";

const MockExam = () => {
    /* quiz de una pregunta: UI estatica con navegacion por rutas */
    return (
        <>
            <div className="main__content">
                {/*  TOP  */}
                <section className="quiz-top">
                    <div className="quiz-top__left">
                        <span className="quiz-top__subject">MATEMÁTICAS</span>
                        <h2>Pregunta 1 de 25</h2>
                    </div>

                    <div className="quiz-top__timer">
                        <i className="fa-regular fa-clock"></i>
                        <span>01:45:22</span>
                    </div>
                </section>

                {/*  PROGRESS  */}
                <div className="progress">
                    <div className="progress__fill"></div>
                </div>

                {/*  QUESTION  */}
                <section className="question">
                    <h1 className="question__title">
                        Un agricultor tiene un terreno rectangular cuya longitud
                        es 3 veces su ancho. Si el área del terreno es de 147
                        metros cuadrados, ¿cuál es el perímetro del terreno?
                    </h1>

                    {/*  OPTIONS  */}
                    <div className="options">
                        <button className="option">
                            <span className="option__letter">A</span>
                            <span>56 metros</span>
                        </button>

                        <button className="option">
                            <span className="option__letter">B</span>
                            <span>42 metros</span>
                        </button>

                        <button className="option">
                            <span className="option__letter">C</span>
                            <span>28 metros</span>
                        </button>

                        <button className="option">
                            <span className="option__letter">D</span>
                            <span>64 metros</span>
                        </button>
                    </div>
                </section>

                {/*  FOOTER  */}
                <section className="quiz-footer">
                    <button className="footer-btn footer-btn--ghost">
                        <i className="fas fa-chevron-left"></i>
                        Anterior
                    </button>

                    <Link to="/mock/result">
                        <button className="footer-btn footer-btn--primary">
                            Siguiente
                            <i className="fas fa-chevron-right"></i>
                        </button>
                    </Link>
                </section>
            </div>

            {/*  ASSISTANT  */}
            <div className="assistant">
                <div className="assistant__message">
                    ¡Concéntrate! Tú puedes con este simulacro.
                </div>

                <div className="assistant__bot">
                    <img src={Zorrito} alt="Zorrito" />
                </div>
            </div>
        </>
    );
};

export default MockExam;
