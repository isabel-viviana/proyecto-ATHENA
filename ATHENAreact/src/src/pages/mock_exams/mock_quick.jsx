import "./mock_quick.css";
import { Link } from "react-router-dom";
import Zorrito from "../../assets/images/Zorrito2D.png";

const MockQuick = () => {
    /* simulacro rapido: UI fija para 1/10 (sin estado real de respuestas) */
    return (
        <>

            <div className="main__content">
                {/*  QUIZ CONTAINER  */}
                <section className="quiz-container">
                    {/*  HEADER  */}
                    <div className="quiz-top">
                        <div className="quiz-top__left">
                            <span className="quiz-top__tag">
                                <i className="fas fa-bolt"></i>
                                SIMULACRO RÁPIDO
                            </span>

                            <h2>Lectura Crítica - Pregunta 1/10</h2>
                        </div>

                        <div className="quiz-top__timer">14:59</div>
                    </div>

                    {/*  QUESTION  */}
                    <div className="question-card">
                        <p className="question-card__label">
                            Lee el siguiente texto y responde:
                        </p>

                        <h3 className="question-card__text">
                            El hombre es un fin en sí mismo, no un medio para
                            los fines de otros...
                        </h3>
                    </div>

                    {/*  OPTIONS  */}
                    <div className="options">
                        <button className="option">
                            <span className="option__letter">A</span>

                            <span className="option__text">
                                Que la humanidad debe instrumentalizarse.
                            </span>
                        </button>

                        <button className="option option--selected">
                            <span className="option__letter">B</span>

                            <span className="option__text">
                                El valor intrínseco de la persona humana según
                                Kant.
                            </span>
                        </button>
                    </div>

                    {/*  FOOTER  */}
                    <div className="quiz-footer">
                        <Link to="/mock/result">
                            <button className="finish-btn">
                                Finalizar Prueba
                            </button>
                        </Link>
                    </div>
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

export default MockQuick;
