import "./profile_edit.css";
import { useState, useEffect } from "react";
import { Link, useNavigate } from "react-router-dom";
import {
    getStudentProfileData,
    updateStudentProfileData,
    changeStudentPassword,
} from "../../api/studentQuestions";

const ProfileEdit = () => {
    const navigate = useNavigate();

    // Estados para campos personales
    const [name, setName] = useState("");
    const [email, setEmail] = useState("");
    const [phone, setPhone] = useState("");
    const [grade, setGrade] = useState("");
    const [goal, setGoal] = useState("350");
    const [studentId, setStudentId] = useState("");

    // Estados para cambio de contraseña
    const [currentPassword, setCurrentPassword] = useState("");
    const [newPassword, setNewPassword] = useState("");
    const [confirmPassword, setConfirmPassword] = useState("");

    // Estados de UI
    const [loading, setLoading] = useState(true);
    const [savingProfile, setSavingProfile] = useState(false);
    const [savingPassword, setSavingPassword] = useState(false);
    const [statusMessage, setStatusMessage] = useState({ text: "", type: "" });
    const [passwordMessage, setPasswordMessage] = useState({ text: "", type: "" });

    useEffect(() => {
        const loadProfile = async () => {
            try {
                setLoading(true);
                const res = await getStudentProfileData();
                if (res && res.success && res.data) {
                    const u = res.data;
                    setName(u.full_name || "");
                    setEmail(u.email || "");
                    setPhone(u.phone === "No registrado" ? "" : u.phone || "");
                    setGrade(u.grade || "Grado 11°");
                    setGoal(String(u.target_icfes_score || 350));
                    setStudentId(String(u.id));
                }
            } catch (err) {
                console.error("Error al cargar datos del perfil:", err);
                setStatusMessage({
                    text: "No se pudieron cargar los datos del perfil.",
                    type: "error",
                });
            } finally {
                setLoading(false);
            }
        };

        loadProfile();
    }, []);

    // Guardar cambios en información personal
    const handleProfileSubmit = async (e) => {
        e.preventDefault();
        setStatusMessage({ text: "", type: "" });

        if (!name.trim()) {
            setStatusMessage({ text: "El nombre completo es obligatorio.", type: "error" });
            return;
        }

        const numericGoal = parseInt(goal, 10);
        if (isNaN(numericGoal) || numericGoal < 0 || numericGoal > 500) {
            setStatusMessage({
                text: "La meta ICFES debe ser un puntaje válido entre 0 y 500.",
                type: "error",
            });
            return;
        }

        try {
            setSavingProfile(true);
            const res = await updateStudentProfileData({
                full_name: name.trim(),
                phone: phone.trim(),
                grade: grade.trim(),
                target_icfes_score: numericGoal,
            });

            if (res && res.success) {
                setStatusMessage({
                    text: "¡Perfil institucional actualizado correctamente!",
                    type: "success",
                });
                setTimeout(() => setStatusMessage({ text: "", type: "" }), 4000);
            } else {
                setStatusMessage({
                    text: res?.message || "Error al actualizar perfil.",
                    type: "error",
                });
            }
        } catch (err) {
            console.error("Error al guardar perfil:", err);
            setStatusMessage({
                text: err?.response?.data?.message || "No se pudo actualizar el perfil.",
                type: "error",
            });
        } finally {
            setSavingProfile(false);
        }
    };

    // Cambiar contraseña institucional
    const handlePasswordSubmit = async (e) => {
        e.preventDefault();
        setPasswordMessage({ text: "", type: "" });

        if (!currentPassword) {
            setPasswordMessage({ text: "Ingresa tu contraseña actual.", type: "error" });
            return;
        }

        if (newPassword.length < 6) {
            setPasswordMessage({
                text: "La nueva contraseña debe tener al menos 6 caracteres.",
                type: "error",
            });
            return;
        }

        if (newPassword !== confirmPassword) {
            setPasswordMessage({
                text: "La confirmación de contraseña no coincide.",
                type: "error",
            });
            return;
        }

        try {
            setSavingPassword(true);
            const res = await changeStudentPassword({
                current_password: currentPassword,
                new_password: newPassword,
            });

            if (res && res.success) {
                setPasswordMessage({
                    text: "¡Contraseña actualizada con éxito!",
                    type: "success",
                });
                setCurrentPassword("");
                setNewPassword("");
                setConfirmPassword("");
                setTimeout(() => setPasswordMessage({ text: "", type: "" }), 4000);
            } else {
                setPasswordMessage({
                    text: res?.message || "Error al cambiar la contraseña.",
                    type: "error",
                });
            }
        } catch (err) {
            console.error("Error al cambiar contraseña:", err);
            setPasswordMessage({
                text: err?.response?.data?.message || "Contraseña actual incorrecta.",
                type: "error",
            });
        } finally {
            setSavingPassword(false);
        }
    };

    if (loading) {
        return (
            <div style={{ textAlign: "center", padding: "80px 20px" }}>
                <div style={{ fontSize: "36px", color: "#38bdf8", marginBottom: "16px" }}>
                    <i className="fas fa-spinner fa-spin"></i>
                </div>
                <h2 style={{ color: "#f8fafc", fontSize: "20px" }}>Cargando formulario...</h2>
            </div>
        );
    }

    return (
        <>
            <div className="bg-blur bg-blur--one"></div>
            <div className="bg-blur bg-blur--two"></div>

            <section className="profile-wrapper">
                <div className="profile-card">
                    <div className="profile-card__header">
                        <div>
                            <h1>Editar Perfil Institucional</h1>
                            <p>Actualiza tu información personal y tus metas académicas.</p>
                        </div>
                        <Link to="/profile">
                            <button
                                type="button"
                                style={{
                                    background: "rgba(148, 163, 184, 0.15)",
                                    border: "1px solid rgba(148, 163, 184, 0.3)",
                                    color: "#cbd5e1",
                                    padding: "8px 16px",
                                    borderRadius: "8px",
                                    cursor: "pointer",
                                    fontSize: "13px",
                                }}
                            >
                                <i className="fas fa-arrow-left" style={{ marginRight: "6px" }}></i>
                                Volver al perfil
                            </button>
                        </Link>
                    </div>

                    {statusMessage.text && (
                        <div
                            style={{
                                padding: "12px 16px",
                                borderRadius: "8px",
                                marginBottom: "20px",
                                fontSize: "14px",
                                background:
                                    statusMessage.type === "success"
                                        ? "rgba(34, 197, 94, 0.15)"
                                        : "rgba(239, 68, 68, 0.15)",
                                border:
                                    statusMessage.type === "success"
                                        ? "1px solid rgba(34, 197, 94, 0.35)"
                                        : "1px solid rgba(239, 68, 68, 0.35)",
                                color: statusMessage.type === "success" ? "#4ade80" : "#f87171",
                            }}
                        >
                            <i
                                className={`fas ${
                                    statusMessage.type === "success"
                                        ? "fa-circle-check"
                                        : "fa-triangle-exclamation"
                                }`}
                                style={{ marginRight: "8px" }}
                            ></i>
                            {statusMessage.text}
                        </div>
                    )}

                    {/* FORMULARIO DE INFORMACIÓN PERSONAL */}
                    <form className="profile-form" onSubmit={handleProfileSubmit}>
                        <div className="form-row">
                            <div className="input-group">
                                <label>Nombre Completo</label>
                                <div className="input-wrapper">
                                    <i className="fas fa-user"></i>
                                    <input
                                        type="text"
                                        value={name}
                                        onChange={(e) => setName(e.target.value)}
                                        placeholder="Tu nombre completo"
                                        required
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="form-grid">
                            <div className="input-group">
                                <label>Correo Institucional (Protegido)</label>
                                <div className="input-wrapper" style={{ opacity: 0.75 }}>
                                    <i className="fas fa-envelope"></i>
                                    <input
                                        type="email"
                                        value={email}
                                        disabled
                                        title="El correo institucional no puede modificarse directamente por seguridad."
                                    />
                                </div>
                            </div>

                            <div className="input-group">
                                <label>Identificación de Estudiante</label>
                                <div className="input-wrapper" style={{ opacity: 0.75 }}>
                                    <i className="fas fa-id-badge"></i>
                                    <input
                                        type="text"
                                        value={`ATHENA-EST-${String(studentId).padStart(5, "0")}`}
                                        disabled
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="form-grid">
                            <div className="input-group">
                                <label>Teléfono de Contacto</label>
                                <div className="input-wrapper">
                                    <i className="fas fa-phone"></i>
                                    <input
                                        type="text"
                                        value={phone}
                                        onChange={(e) => setPhone(e.target.value)}
                                        placeholder="Ej: 3001234567"
                                    />
                                </div>
                            </div>

                            <div className="input-group">
                                <label>Grado / Programa Académico</label>
                                <div className="input-wrapper">
                                    <i className="fas fa-graduation-cap"></i>
                                    <input
                                        type="text"
                                        value={grade}
                                        onChange={(e) => setGrade(e.target.value)}
                                        placeholder="Ej: Grado 11° A"
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="form-row">
                            <div className="input-group">
                                <label>Meta de Puntaje ICFES (0 - 500)</label>
                                <div className="input-wrapper">
                                    <i className="fas fa-bullseye"></i>
                                    <input
                                        type="number"
                                        value={goal}
                                        onChange={(e) => setGoal(e.target.value)}
                                        min="0"
                                        max="500"
                                        placeholder="Ej: 380"
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="form-actions" style={{ display: "flex", gap: "12px", justifyContent: "flex-end", marginTop: "12px" }}>
                            <button
                                type="submit"
                                className="btn-save"
                                disabled={savingProfile}
                                style={{ cursor: savingProfile ? "wait" : "pointer" }}
                            >
                                {savingProfile ? "Guardando..." : "Guardar Información"}
                            </button>
                        </div>
                    </form>

                    {/* SECCIÓN CAMBIO DE CONTRASEÑA */}
                    <div style={{ marginTop: "40px", paddingTop: "30px", borderTop: "1px solid rgba(148, 163, 184, 0.2)" }}>
                        <h3 style={{ color: "#f8fafc", fontSize: "18px", marginBottom: "8px" }}>
                            <i className="fas fa-lock" style={{ marginRight: "8px", color: "#38bdf8" }}></i>
                            Cambiar Contraseña
                        </h3>
                        <p style={{ color: "#94a3b8", fontSize: "13px", marginBottom: "20px" }}>
                            Ingresa tu contraseña actual y define una nueva clave con al menos 6 caracteres.
                        </p>

                        {passwordMessage.text && (
                            <div
                                style={{
                                    padding: "12px 16px",
                                    borderRadius: "8px",
                                    marginBottom: "20px",
                                    fontSize: "14px",
                                    background:
                                        passwordMessage.type === "success"
                                            ? "rgba(34, 197, 94, 0.15)"
                                            : "rgba(239, 68, 68, 0.15)",
                                    border:
                                        passwordMessage.type === "success"
                                            ? "1px solid rgba(34, 197, 94, 0.35)"
                                            : "1px solid rgba(239, 68, 68, 0.35)",
                                    color: passwordMessage.type === "success" ? "#4ade80" : "#f87171",
                                }}
                            >
                                <i
                                    className={`fas ${
                                        passwordMessage.type === "success"
                                            ? "fa-circle-check"
                                            : "fa-triangle-exclamation"
                                    }`}
                                    style={{ marginRight: "8px" }}
                                ></i>
                                {passwordMessage.text}
                            </div>
                        )}

                        <form onSubmit={handlePasswordSubmit} className="profile-form">
                            <div className="form-row">
                                <div className="input-group">
                                    <label>Contraseña Actual</label>
                                    <div className="input-wrapper">
                                        <i className="fas fa-key"></i>
                                        <input
                                            type="password"
                                            value={currentPassword}
                                            onChange={(e) => setCurrentPassword(e.target.value)}
                                            placeholder="Contraseña actual"
                                            required
                                        />
                                    </div>
                                </div>
                            </div>

                            <div className="form-grid">
                                <div className="input-group">
                                    <label>Nueva Contraseña</label>
                                    <div className="input-wrapper">
                                        <i className="fas fa-lock"></i>
                                        <input
                                            type="password"
                                            value={newPassword}
                                            onChange={(e) => setNewPassword(e.target.value)}
                                            placeholder="Mínimo 6 caracteres"
                                            required
                                        />
                                    </div>
                                </div>

                                <div className="input-group">
                                    <label>Confirmar Nueva Contraseña</label>
                                    <div className="input-wrapper">
                                        <i className="fas fa-lock"></i>
                                        <input
                                            type="password"
                                            value={confirmPassword}
                                            onChange={(e) => setConfirmPassword(e.target.value)}
                                            placeholder="Repite la nueva contraseña"
                                            required
                                        />
                                    </div>
                                </div>
                            </div>

                            <div className="form-actions" style={{ display: "flex", gap: "12px", justifyContent: "flex-end", marginTop: "12px" }}>
                                <button
                                    type="submit"
                                    className="btn-save"
                                    disabled={savingPassword}
                                    style={{
                                        background: "linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)",
                                        cursor: savingPassword ? "wait" : "pointer",
                                    }}
                                >
                                    {savingPassword ? "Actualizando clave..." : "Actualizar Contraseña"}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        </>
    );
};

export default ProfileEdit;
