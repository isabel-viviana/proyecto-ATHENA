import "./payments.css";
import { Link } from "react-router-dom";

const Payments = () => {
    return (
        <>
            <div className="checkout-wrapper">
                <Link to="/subscriptions">← Volver a planes</Link>

                <h1 className="checkout-title">Resumen de Compra</h1>

                <div className="checkout-grid">
                    {/*  PAYMENT  */}
                    <section className="payment-card">
                        <div className="payment-card__header">
                            <i className="far fa-credit-card"></i>
                            <h2>Método de Pago</h2>
                        </div>

                        <form className="payment-form">
                            <div className="form-group">
                                <label>NÚMERO DE TARJETA</label>

                                <div className="input-icon">
                                    <i className="far fa-credit-card"></i>

                                    <input
                                        type="text"
                                        placeholder="0000 0000 0000 0000"
                                    />
                                </div>
                            </div>

                            <div className="form-row">
                                <div className="form-group">
                                    <label>VENCIMIENTO</label>

                                    <input type="text" placeholder="MM/YY" />
                                </div>

                                <div className="form-group">
                                    <label>CVC</label>

                                    <input type="text" placeholder="123" />
                                </div>
                            </div>

                            <div className="form-group">
                                <label>NOMBRE EN LA TARJETA</label>

                                <input
                                    type="text"
                                    placeholder="Como aparece en la tarjeta"
                                />
                            </div>
                        </form>

                        <div className="payment-secure">
                            <i className="fas fa-shield-alt"></i>
                            Pago 100% seguro y encriptado
                        </div>
                    </section>

                    {/*  ORDER  */}
                    <aside className="summary-card">
                        <h2 className="summary-card__title">
                            Detalle del pedido
                        </h2>

                        <div className="summary-item">
                            <div>
                                <h4>ATHENA Premium</h4>
                                <span>(1 Mes)</span>
                            </div>

                            <p>$49.900</p>
                        </div>

                        <div className="summary-item summary-item--muted">
                            <span>Impuestos</span>
                            <span>Incluidos</span>
                        </div>

                        <div className="summary-divider"></div>

                        <div className="summary-total">
                            <span>Total</span>
                            <h3>$49.900</h3>
                        </div>

                        <button className="pay-button">Pagar $49.900</button>
                    </aside>
                </div>
            </div>
        </>
    );
};

export default Payments;
