import "./mock_history.css";
import { Link } from "react-router-dom";
import Zorrito from "../../assets/images/Zorrito2D.png";

const MockHistory = () => {
    /* historial de simulacros: lista estatica pensada para lectura rapida */
    return (

        <>
            <div className="main__content">
                {/*  TITLE  */}
                <section className="page-header">
                    <div className="page-header__info">
                        <h1>Historial de Pruebas</h1>
                    </div>
                </section>

                {/*  HISTORY  */}
                <section className="history">
                    {/*  CARD  */}
                    <article className="history-card">
                        <div className="history-card__left">
                            <div className="history-card__number">#1</div>

                            <div className="history-card__info">
                                <h3>Simulacro Completo</h3>
                                <p>Hace 1 día - 4h 15m</p>
                            </div>
                        </div>

                        <div className="history-card__right">
                            <div className="history-card__score">
                                <span className="score">305</span>
                                <span className="points">PUNTOS</span>
                            </div>

                            <Link to="/mock/result">
                                <button className="history-card__btn">
                                    Ver
                                </button>
                            </Link>
                        </div>
                    </article>

                    {/*  CARD  */}
                    <article className="history-card">
                        <div className="history-card__left">
                            <div className="history-card__number">#2</div>

                            <div className="history-card__info">
                                <h3>Simulacro Completo</h3>
                                <p>Hace 2 días - 4h 15m</p>
                            </div>
                        </div>

                        <div className="history-card__right">
                            <div className="history-card__score">
                                <span className="score">310</span>
                                <span className="points">PUNTOS</span>
                            </div>

                            <Link to="/mock/result">
                                <button className="history-card__btn">
                                    Ver
                                </button>
                            </Link>
                        </div>
                    </article>

                    {/*  CARD  */}
                    <article className="history-card">
                        <div className="history-card__left">
                            <div className="history-card__number">#3</div>

                            <div className="history-card__info">
                                <h3>Simulacro Completo</h3>
                                <p>Hace 3 días - 4h 15m</p>
                            </div>
                        </div>

                        <div className="history-card__right">
                            <div className="history-card__score">
                                <span className="score">315</span>
                                <span className="points">PUNTOS</span>
                            </div>

                            <Link to="/mock/result">
                                <button className="history-card__btn">
                                    Ver
                                </button>
                            </Link>
                        </div>
                    </article>

                    {/*  CARD  */}
                    <article className="history-card">
                        <div className="history-card__left">
                            <div className="history-card__number">#4</div>

                            <div className="history-card__info">
                                <h3>Simulacro Completo</h3>
                                <p>Hace 4 días - 4h 15m</p>
                            </div>
                        </div>

                        <div className="history-card__right">
                            <div className="history-card__score">
                                <span className="score">320</span>
                                <span className="points">PUNTOS</span>
                            </div>

                            <Link to="/mock/result">
                                <button className="history-card__btn">
                                    Ver
                                </button>
                            </Link>
                        </div>
                    </article>

                    {/*  CARD  */}
                    <article className="history-card">
                        <div className="history-card__left">
                            <div className="history-card__number">#5</div>

                            <div className="history-card__info">
                                <h3>Simulacro Completo</h3>
                                <p>Hace 5 días - 4h 15m</p>
                            </div>
                        </div>

                        <div className="history-card__right">
                            <div className="history-card__score">
                                <span className="score">325</span>
                                <span className="points">PUNTOS</span>
                            </div>

                            <Link to="/mock/result">
                                <button className="history-card__btn">
                                    Ver
                                </button>
                            </Link>
                        </div>
                    </article>
                </section>
            </div>

            {/*  BOT IA  */}
            <div className="assistant">
                <div className="assistant__message">
                    ¡Sigue así! Estás haciendo un gran trabajo.
                </div>

                <div className="assistant__bot">
                    <img src={Zorrito} alt="Zorrito" />
                </div>
            </div>
        </>
    );
};

export default MockHistory;
