import "./store.css";

const Store = () => {
    return (
        <>
            <div className="main__content">
                {/*  HEADER  */}
                <section className="store-header">
                    <div className="store-header__left">
                        <div className="store-header__icon">
                            <i className="fas fa-store"></i>
                        </div>

                        <div>
                            <h1>Tienda ATHENA</h1>
                            <p>
                                Canjea tus monedas por recompensas y
                                personalizaciones.
                            </p>
                        </div>
                    </div>

                    <div className="coins">
                        <i className="fas fa-star"></i>
                        <span>1,250</span>
                    </div>
                </section>

                {/*  SECTION  */}
                <section className="shop-section">
                    <div className="section-title">
                        <h2>Avatares y Personalización</h2>
                        <span>12 artículos</span>
                    </div>

                    <div className="products-grid">
                        {/*  PRODUCT  */}
                        <div className="product-card">
                            <div className="product-card__avatar avatar-blue">
                                <i className="fas fa-hat-wizard"></i>
                            </div>

                            <h3>Avatar Búho Mágico</h3>

                            <p>Diseño exclusivo ATHENA</p>

                            <button className="buy-btn">
                                <i className="fas fa-star"></i>
                                500
                            </button>
                        </div>

                        {/*  PRODUCT  */}
                        <div className="product-card">
                            <div className="product-card__avatar avatar-purple">
                                <i className="fas fa-user-astronaut"></i>
                            </div>

                            <h3>Avatar Galáctico</h3>

                            <p>Estilo espacial premium</p>

                            <button className="buy-btn">
                                <i className="fas fa-star"></i>
                                650
                            </button>
                        </div>

                        {/*  PRODUCT  */}
                        <div className="product-card">
                            <div className="product-card__avatar avatar-green">
                                <i className="fas fa-leaf"></i>
                            </div>

                            <h3>Tema Naturaleza</h3>

                            <p>Colores verdes relajantes</p>

                            <button className="buy-btn">
                                <i className="fas fa-star"></i>
                                300
                            </button>
                        </div>

                        {/*  PRODUCT  */}
                        <div className="product-card">
                            <div className="product-card__avatar avatar-yellow">
                                <i className="fas fa-crown"></i>
                            </div>

                            <h3>Marco Legendario</h3>

                            <p>Marco dorado brillante</p>

                            <button className="buy-btn">
                                <i className="fas fa-star"></i>
                                800
                            </button>
                        </div>

                        {/*  PRODUCT  */}
                        <div className="product-card">
                            <div className="product-card__avatar avatar-red">
                                <i className="fas fa-fire"></i>
                            </div>

                            <h3>Efecto Fuego</h3>

                            <p>Animación especial de perfil</p>

                            <button className="buy-btn">
                                <i className="fas fa-star"></i>
                                720
                            </button>
                        </div>

                        {/*  PRODUCT  */}
                        <div className="product-card">
                            <div className="product-card__avatar avatar-cyan">
                                <i className="fas fa-water"></i>
                            </div>

                            <h3>Tema Océano</h3>

                            <p>Interfaz azul dinámica</p>

                            <button className="buy-btn">
                                <i className="fas fa-star"></i>
                                450
                            </button>
                        </div>

                        {/*  PRODUCT  */}
                        <div className="product-card">
                            <div className="product-card__avatar avatar-pink">
                                <i className="fas fa-heart"></i>
                            </div>

                            <h3>Pack Rosa Neon</h3>

                            <p>Estilo moderno y brillante</p>

                            <button className="buy-btn">
                                <i className="fas fa-star"></i>
                                520
                            </button>
                        </div>

                        {/*  PRODUCT  */}
                        <div className="product-card">
                            <div className="product-card__avatar avatar-orange">
                                <i className="fas fa-bolt"></i>
                            </div>

                            <h3>Efecto Energía</h3>

                            <p>Rayos animados para perfil</p>

                            <button className="buy-btn">
                                <i className="fas fa-star"></i>
                                600
                            </button>
                        </div>
                    </div>
                </section>

                {/*  INVENTORY BUTTON  */}
                <div className="inventory">
                    <a href="#">
                        <i className="fas fa-box-open"></i>
                        Ver mi inventario
                    </a>
                </div>
            </div>
        </>
    );
};

export default Store;
