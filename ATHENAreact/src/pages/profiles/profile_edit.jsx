import "./profile_edit.css";
import { useState } from "react";

const ProfileEdit = () => {
    const [name, setName] = useState("Andrés Estudiante");
    const [school, setSchool] = useState("Colegio San Juan Bosco");
    const [goal, setGoal] = useState("350");
    const [email, setEmail] = useState("andres@athena.com");
    const [status, setStatus] = useState("");

    const handleSubmit = (e) => {
        e.preventDefault();
        setStatus("");

        if (!name.trim() || !school.trim() || !email.trim()) {
            setStatus("Por favor completa todos los campos.");
            return;
        }

        setStatus("Cambios guardados correctamente.");
        setTimeout(() => setStatus(""), 3000);
    };

    return (
        <>
            <div className="bg-blur bg-blur--one"></div>
            <div className="bg-blur bg-blur--two"></div>

            <section className="profile-wrapper">
                <div className="profile-card">
                    <div className="profile-card__header">
                        <div>
                            <h1>Editar Perfil</h1>
                            <p>
                                Actualiza tu información personal y académica.
                            </p>
                        </div>
                        <div className="profile-level">
                            <span className="profile-level__badge">
                                Nivel Alto
                            </span>
                        </div>
                    </div>

                    <form className="profile-form" onSubmit={handleSubmit}>
                        <div className="form-row">
                            <div className="input-group">
                                <label>Nombre Completo</label>
                                <div className="input-wrapper">
                                    <i className="fas fa-user"></i>
                                    <input
                                        type="text"
                                        value={name}
                                        onChange={(e) =>
                                            setName(e.target.value)
                                        }
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="form-row">
                            <div className="input-group">
                                <label>Colegio</label>
                                <div className="input-wrapper">
                                    <i className="fas fa-school"></i>
                                    <input
                                        type="text"
                                        value={school}
                                        onChange={(e) =>
                                            setSchool(e.target.value)
                                        }
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="form-grid">
                            <div className="input-group">
                                <label>Meta ICFES</label>
                                <div className="input-wrapper">
                                    <i className="fas fa-chart-line"></i>
                                    <input
                                        type="number"
                                        value={goal}
                                        onChange={(e) =>
                                            setGoal(e.target.value)
                                        }
                                    />
                                </div>
                            </div>

                            <div className="input-group">
                                <label>Correo Electrónico</label>
                                <div className="input-wrapper">
                                    <i className="fas fa-envelope"></i>
                                    <input
                                        type="email"
                                        value={email}
                                        onChange={(e) =>
                                            setEmail(e.target.value)
                                        }
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="profile-stats">
                            <div className="stat-box">
                                <span>Simulacros</span>
                                <strong>12</strong>
                            </div>
                            <div className="stat-box">
                                <span>Puntaje Máx.</span>
                                <strong>340</strong>
                            </div>
                            <div className="stat-box">
                                <span>Racha</span>
                                <strong>12 días</strong>
                            </div>
                        </div>

                        {status && <p className="form-feedback">{status}</p>}

                        <div className="profile-actions">
                            <button type="button" className="btn btn--ghost">
                                Cancelar
                            </button>
                            <button type="submit" className="btn btn--primary">
                                <i className="fas fa-save"></i>
                                Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </section>

            <div className="assistant-help">
                <div className="assistant-help__message">
                    ¡Sigue así! Estás haciendo un gran trabajo.
                </div>
                <div className="assistant-help__avatar">🤖</div>
            </div>
        </>
    );
};

export default ProfileEdit;
