import "./subscription.css";
import { Link } from "react-router-dom";

const Subscription = () => {
    return (
        <>
            <div className="main__content">
                {/*  HEADER  */}
                <section className="page-header">
                    <div className="page-header__info">
                        <h1>Mi Suscripción</h1>
                        <p>
                            Administra tu plan y revisa tu historial de pagos.
                        </p>
                    </div>
                </section>

                {/*  PLAN CARD  */}
                <section className="subscription-card">
                    <div className="subscription-card__top">
                        <div className="subscription-card__plan">
                            <div className="subscription-card__badges">
                                <span className="plan-badge">
                                    <i className="fas fa-crown"></i>
                                    PLAN PREMIUM
                                </span>

                                <span className="status-badge">
                                    <i className="fas fa-circle"></i>
                                    Activo
                                </span>
                            </div>

                            <h2>Facturación mensual</h2>

                            <p>
                                Próximo cobro:
                                <span>15 de Mayo, 2026</span>
                                ($49.900)
                            </p>
                        </div>

                        <div className="subscription-card__actions">
                            <Link to="/payments">
                                <button className="btn btn--primary">
                                    Actualizar Pago
                                </button>
                            </Link>

                            <button className="btn btn--secondary">
                                Cancelar
                            </button>
                        </div>
                    </div>

                    <div className="subscription-card__bottom">
                        <div className="subscription-stat">
                            <span className="subscription-stat__label">
                                Plan Actual
                            </span>

                            <h3>Premium</h3>
                        </div>

                        <div className="subscription-stat">
                            <span className="subscription-stat__label">
                                Renovación
                            </span>

                            <h3>15 Mayo</h3>
                        </div>

                        <div className="subscription-stat">
                            <span className="subscription-stat__label">
                                Método
                            </span>

                            <h3>Visa •••• 4242</h3>
                        </div>
                    </div>
                </section>

                {/*  PAYMENT HISTORY  */}
                <section className="history-card">
                    <div className="history-card__header">
                        <h2>Historial de Pagos</h2>
                        <span>Últimos movimientos</span>
                    </div>

                    <div className="payment-item">
                        <div className="payment-item__left">
                            <div className="payment-icon">
                                <i className="fas fa-receipt"></i>
                            </div>

                            <div className="payment-info">
                                <h3>15 Abril 2026</h3>
                                <p>Visa •••• 4242</p>
                            </div>
                        </div>

                        <div className="payment-item__right">
                            <h4>$49.900</h4>

                            <span className="payment-status">Factura</span>
                        </div>
                    </div>

                    <div className="payment-item">
                        <div className="payment-item__left">
                            <div className="payment-icon">
                                <i className="fas fa-receipt"></i>
                            </div>

                            <div className="payment-info">
                                <h3>15 Marzo 2026</h3>
                                <p>Visa •••• 4242</p>
                            </div>
                        </div>

                        <div className="payment-item__right">
                            <h4>$49.900</h4>

                            <span className="payment-status">Factura</span>
                        </div>
                    </div>

                    <div className="payment-item">
                        <div className="payment-item__left">
                            <div className="payment-icon">
                                <i className="fas fa-receipt"></i>
                            </div>

                            <div className="payment-info">
                                <h3>15 Febrero 2026</h3>
                                <p>Visa •••• 4242</p>
                            </div>
                        </div>

                        <div className="payment-item__right">
                            <h4>$49.900</h4>

                            <span className="payment-status">Factura</span>
                        </div>
                    </div>
                </section>
            </div>
        </>
    );
};

export default Subscription;
