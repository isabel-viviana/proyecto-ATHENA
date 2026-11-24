import "./profile_achievements.css";

const ProfileAchievements = () => {
    return (
        <>
            <div className="main__content">
                {/*  HEADER  */}
                <section className="page-header">
                    <div className="page-header__info">
                        <h1>Tus Logros</h1>
                        <p>
                            Completa desafíos para ganar medallas, monedas y
                            recompensas.
                        </p>
                    </div>

                    <div className="page-header__stats">
                        <div className="header-stat">
                            <span className="header-stat__value">18</span>
                            <span className="header-stat__label">
                                Desbloqueados
                            </span>
                        </div>

                        <div className="header-stat">
                            <span className="header-stat__value">34</span>
                            <span className="header-stat__label">Totales</span>
                        </div>
                    </div>
                </section>

                {/*  CATEGORIAS  */}
                <section className="categories">
                    <button className="category-btn category-btn--active">
                        Todos
                    </button>

                    <button className="category-btn">Estudio</button>

                    <button className="category-btn">Simulacros</button>

                    <button className="category-btn">Rachas</button>

                    <button className="category-btn">Especiales</button>
                </section>

                {/*  GRID  */}
                <section className="achievements-grid">
                    {/*  LOGRO  */}
                    <div className="achievement-card achievement-card--gold">
                        <div className="achievement-card__icon">
                            <i className="fas fa-award"></i>
                        </div>

                        <h3 className="achievement-card__title">Primer Paso</h3>

                        <p className="achievement-card__desc">
                            Completa tu primer simulacro.
                        </p>

                        <span className="achievement-card__status achievement-card__status--completed">
                            Desbloqueado
                        </span>
                    </div>

                    {/*  LOGRO  */}
                    <div className="achievement-card achievement-card--gold">
                        <div className="achievement-card__icon">
                            <i className="fas fa-fire"></i>
                        </div>

                        <h3 className="achievement-card__title">Racha de 7</h3>

                        <p className="achievement-card__desc">
                            Estudia durante 7 días seguidos.
                        </p>

                        <span className="achievement-card__status achievement-card__status--completed">
                            Desbloqueado
                        </span>
                    </div>

                    {/*  LOGRO  */}
                    <div className="achievement-card achievement-card--locked">
                        <div className="achievement-card__icon achievement-card__icon--locked">
                            <i className="fas fa-book-open"></i>
                        </div>

                        <h3 className="achievement-card__title">
                            Lector Voraz
                        </h3>

                        <p className="achievement-card__desc">
                            Obtén 100% en Lectura Crítica.
                        </p>

                        <span className="achievement-card__status">
                            Bloqueado
                        </span>
                    </div>

                    {/*  LOGRO  */}
                    <div className="achievement-card achievement-card--locked">
                        <div className="achievement-card__icon achievement-card__icon--locked">
                            <i className="fas fa-moon"></i>
                        </div>

                        <h3 className="achievement-card__title">Madrugador</h3>

                        <p className="achievement-card__desc">
                            Estudia antes de las 6 AM.
                        </p>

                        <span className="achievement-card__status">
                            Bloqueado
                        </span>
                    </div>

                    {/*  NUEVOS LOGROS  */}

                    <div className="achievement-card achievement-card--silver">
                        <div className="achievement-card__icon">
                            <i className="fas fa-calculator"></i>
                        </div>

                        <h3 className="achievement-card__title">Matemático</h3>

                        <p className="achievement-card__desc">
                            Supera 20 ejercicios de matemáticas.
                        </p>

                        <span className="achievement-card__status achievement-card__status--completed">
                            Desbloqueado
                        </span>
                    </div>

                    <div className="achievement-card achievement-card--silver">
                        <div className="achievement-card__icon">
                            <i className="fas fa-brain"></i>
                        </div>

                        <h3 className="achievement-card__title">
                            Mente Brillante
                        </h3>

                        <p className="achievement-card__desc">
                            Alcanza más de 300 puntos globales.
                        </p>

                        <span className="achievement-card__status achievement-card__status--completed">
                            Desbloqueado
                        </span>
                    </div>

                    <div className="achievement-card achievement-card--locked">
                        <div className="achievement-card__icon achievement-card__icon--locked">
                            <i className="fas fa-bolt"></i>
                        </div>

                        <h3 className="achievement-card__title">Velocista</h3>

                        <p className="achievement-card__desc">
                            Finaliza un simulacro en menos tiempo.
                        </p>

                        <span className="achievement-card__status">65%</span>
                    </div>

                    <div className="achievement-card achievement-card--gold">
                        <div className="achievement-card__icon">
                            <i className="fas fa-star"></i>
                        </div>

                        <h3 className="achievement-card__title">
                            Estrella ATHENA
                        </h3>

                        <p className="achievement-card__desc">
                            Completa 50 prácticas exitosas.
                        </p>

                        <span className="achievement-card__status achievement-card__status--completed">
                            Desbloqueado
                        </span>
                    </div>

                    <div className="achievement-card achievement-card--locked">
                        <div className="achievement-card__icon achievement-card__icon--locked">
                            <i className="fas fa-globe"></i>
                        </div>

                        <h3 className="achievement-card__title">Bilingüe</h3>

                        <p className="achievement-card__desc">
                            Consigue nivel alto en inglés.
                        </p>

                        <span className="achievement-card__status">40%</span>
                    </div>

                    <div className="achievement-card achievement-card--silver">
                        <div className="achievement-card__icon">
                            <i className="fas fa-flask"></i>
                        </div>

                        <h3 className="achievement-card__title">Científico</h3>

                        <p className="achievement-card__desc">
                            Responde 100 preguntas de ciencias.
                        </p>

                        <span className="achievement-card__status achievement-card__status--completed">
                            Desbloqueado
                        </span>
                    </div>

                    <div className="achievement-card achievement-card--locked">
                        <div className="achievement-card__icon achievement-card__icon--locked">
                            <i className="fas fa-crown"></i>
                        </div>

                        <h3 className="achievement-card__title">
                            Maestro Supremo
                        </h3>

                        <p className="achievement-card__desc">
                            Obtén el puntaje máximo ATHENA.
                        </p>

                        <span className="achievement-card__status">82%</span>
                    </div>

                    <div className="achievement-card achievement-card--silver">
                        <div className="achievement-card__icon">
                            <i className="fas fa-clock"></i>
                        </div>

                        <h3 className="achievement-card__title">Constancia</h3>

                        <p className="achievement-card__desc">
                            Estudia 30 horas acumuladas.
                        </p>

                        <span className="achievement-card__status achievement-card__status--completed">
                            Desbloqueado
                        </span>
                    </div>
                </section>
            </div>
        </>
    );
};

export default ProfileAchievements;
