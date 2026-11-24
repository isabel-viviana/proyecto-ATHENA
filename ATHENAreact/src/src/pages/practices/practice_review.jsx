import "./practice_review.css";

const PracticeReview = () => {
    return (
        <>
            {/*  HEADER  */}
            <section className="page-header">
                <div className="page-header__content">
                    <span className="page-header__breadcrumb">
                        <i className="fas fa-arrow-left"></i>
                        Volver a resultados
                    </span>

                    <h1>Detalle de Respuestas</h1>

                    <p>
                        Revisa tu rendimiento pregunta por pregunta y detecta en
                        qué áreas puedes mejorar.
                    </p>
                </div>

                <div className="page-header__stats">
                    <div className="header-mini-card">
                        <span>Aciertos</span>
                        <strong>7</strong>
                    </div>

                    <div className="header-mini-card header-mini-card--danger">
                        <span>Errores</span>
                        <strong>3</strong>
                    </div>

                    <div className="header-mini-card">
                        <span>Precisión</span>
                        <strong>70%</strong>
                    </div>
                </div>
            </section>

            {/*  FILTERS  */}
            <section className="filters">
                <button className="filter-btn filter-btn--active">Todas</button>

                <button className="filter-btn">Correctas</button>

                <button className="filter-btn">Incorrectas</button>

                <button className="filter-btn">Matemáticas</button>

                <button className="filter-btn">Lectura</button>
            </section>

            {/*  QUESTIONS  */}
            <section className="questions">
                {/*  QUESTION  */}
                <article className="question-card question-card--correct">
                    <div className="question-card__left">
                        <div className="question-icon question-icon--correct">
                            <i className="fas fa-check"></i>
                        </div>

                        <div className="question-info">
                            <div className="question-top">
                                <span className="question-category">
                                    MATEMÁTICAS · PREGUNTA 1
                                </span>

                                <span className="question-difficulty difficulty--easy">
                                    Fácil
                                </span>
                            </div>

                            <h3>
                                Un agricultor tiene 24 hectáreas y decide
                                dividirlas en partes iguales...
                            </h3>

                            <div className="question-meta">
                                <span>
                                    <i className="fas fa-clock"></i>
                                    1m 20s
                                </span>

                                <span>
                                    <i className="fas fa-bolt"></i>
                                    Respuesta rápida
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="question-actions">
                        <button className="btn-outline">Ver Explicación</button>
                    </div>
                </article>

                {/*  QUESTION  */}
                <article className="question-card question-card--wrong">
                    <div className="question-card__left">
                        <div className="question-icon question-icon--wrong">
                            <i className="fas fa-xmark"></i>
                        </div>

                        <div className="question-info">
                            <div className="question-top">
                                <span className="question-category">
                                    MATEMÁTICAS · PREGUNTA 2
                                </span>

                                <span className="question-difficulty difficulty--medium">
                                    Media
                                </span>
                            </div>

                            <h3>
                                Si f(x) = 2x² - 4x + 1, determine el valor de
                                f(3)...
                            </h3>

                            <div className="question-meta">
                                <span>
                                    <i className="fas fa-clock"></i>
                                    2m 45s
                                </span>

                                <span>
                                    <i className="fas fa-triangle-exclamation"></i>
                                    Error conceptual
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="question-actions">
                        <button className="btn-outline">Ver Explicación</button>
                    </div>
                </article>

                {/*  QUESTION  */}
                <article className="question-card question-card--wrong">
                    <div className="question-card__left">
                        <div className="question-icon question-icon--wrong">
                            <i className="fas fa-xmark"></i>
                        </div>

                        <div className="question-info">
                            <div className="question-top">
                                <span className="question-category">
                                    LECTURA · PREGUNTA 3
                                </span>

                                <span className="question-difficulty difficulty--hard">
                                    Difícil
                                </span>
                            </div>

                            <h3>
                                Según el texto, el autor considera que la
                                tecnología...
                            </h3>

                            <div className="question-meta">
                                <span>
                                    <i className="fas fa-clock"></i>
                                    45s
                                </span>

                                <span>
                                    <i className="fas fa-book-open"></i>
                                    Interpretación textual
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="question-actions">
                        <button className="btn-outline">Ver Explicación</button>
                    </div>
                </article>

                {/*  QUESTION  */}
                <article className="question-card question-card--correct">
                    <div className="question-card__left">
                        <div className="question-icon question-icon--correct">
                            <i className="fas fa-check"></i>
                        </div>

                        <div className="question-info">
                            <div className="question-top">
                                <span className="question-category">
                                    CIENCIAS · PREGUNTA 4
                                </span>

                                <span className="question-difficulty difficulty--easy">
                                    Fácil
                                </span>
                            </div>

                            <h3>
                                ¿Cuál es la principal función de la mitocondria?
                            </h3>

                            <div className="question-meta">
                                <span>
                                    <i className="fas fa-clock"></i>
                                    38s
                                </span>

                                <span>
                                    <i className="fas fa-star"></i>
                                    Excelente precisión
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="question-actions">
                        <button className="btn-outline">Ver Explicación</button>
                    </div>
                </article>

                {/*  QUESTION  */}
                <article className="question-card question-card--correct">
                    <div className="question-card__left">
                        <div className="question-icon question-icon--correct">
                            <i className="fas fa-check"></i>
                        </div>

                        <div className="question-info">
                            <div className="question-top">
                                <span className="question-category">
                                    INGLÉS · PREGUNTA 5
                                </span>

                                <span className="question-difficulty difficulty--medium">
                                    Media
                                </span>
                            </div>

                            <h3>
                                Choose the correct option to complete the
                                sentence...
                            </h3>

                            <div className="question-meta">
                                <span>
                                    <i className="fas fa-clock"></i>
                                    1m 05s
                                </span>

                                <span>
                                    <i className="fas fa-language"></i>
                                    Gramática
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="question-actions">
                        <button className="btn-outline">Ver Explicación</button>
                    </div>
                </article>

                {/*  QUESTION  */}
                <article className="question-card question-card--wrong">
                    <div className="question-card__left">
                        <div className="question-icon question-icon--wrong">
                            <i className="fas fa-xmark"></i>
                        </div>

                        <div className="question-info">
                            <div className="question-top">
                                <span className="question-category">
                                    SOCIALES · PREGUNTA 6
                                </span>

                                <span className="question-difficulty difficulty--medium">
                                    Media
                                </span>
                            </div>

                            <h3>
                                La Constitución de 1991 se caracteriza
                                principalmente por...
                            </h3>

                            <div className="question-meta">
                                <span>
                                    <i className="fas fa-clock"></i>
                                    3m 12s
                                </span>

                                <span>
                                    <i className="fas fa-chart-line"></i>
                                    Tiempo elevado
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="question-actions">
                        <button className="btn-outline">Ver Explicación</button>
                    </div>
                </article>
            </section>
        </>
    );
};

export default PracticeReview;
