import "./profile.css";
import { Link, useNavigate } from "react-router-dom";
import { useState, useEffect } from "react";
import { useAuthStore } from "../../stores/useAuthStore";
import { getStudentProfileData, getStudentAchievements } from "../../api/studentQuestions";
import { fetchWompiHistory } from "../../services/wompiService";

const Profile = () => {
    const navigate = useNavigate();
    const user = useAuthStore((state) => state.user);
    const logout = useAuthStore((state) => state.logout);
    const [profile, setProfile] = useState(null);
    const [achievementsSummary, setAchievementsSummary] = useState(null);
    const [activeSubscription, setActiveSubscription] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");

    useEffect(() => {
        const fetchProfile = async () => {
            try {
                setLoading(true);
                const [res, achRes, subRes] = await Promise.allSettled([
                    getStudentProfileData(),
                    getStudentAchievements(),
                    fetchWompiHistory(),
                ]);

                if (res.status === "fulfilled" && res.value?.success && res.value?.data) {
                    setProfile(res.value.data);
                } else if (user) {
                    setProfile(user);
                } else {
                    setError(res.value?.message || "No se pudo obtener el perfil.");
                }

                if (achRes.status === "fulfilled" && achRes.value?.success && achRes.value?.data?.summary) {
                    setAchievementsSummary(achRes.value.data.summary);
                }

                if (subRes.status === "fulfilled" && subRes.value?.active_subscription) {
                    setActiveSubscription(subRes.value.active_subscription);
                }
            } catch (err) {
                if (user) {
                    setProfile(user);
                } else {
                    console.error("Error al cargar perfil:", err);
                    setError(err?.response?.data?.message || "Error al conectar con el servidor.");
                }
            } finally {
                setLoading(false);
            }
        };

        fetchProfile();
    }, [user]);

    const handleLogout = async () => {
        try {
            await logout();
        } catch {
            // Ignorar errores de red durante el cierre de sesión
        }
        navigate("/login", { replace: true });
    };

    if (loading) {
        return (
            <div className="profile-page" style={{ textAlign: "center", padding: "80px 20px" }}>
                <div style={{ fontSize: "36px", color: "#38bdf8", marginBottom: "16px" }}>
                    <i className="fas fa-spinner fa-spin"></i>
                </div>
                <h2 style={{ color: "#f8fafc", fontSize: "20px" }}>Cargando perfil institucional...</h2>
                <p style={{ color: "#94a3b8" }}>Recuperando tus datos oficiales de ATHENA.</p>
            </div>
        );
    }

    if (error && !profile) {
        return (
            <div className="profile-page" style={{ textAlign: "center", padding: "80px 20px" }}>
                <div style={{ fontSize: "36px", color: "#ef4444", marginBottom: "16px" }}>
                    <i className="fas fa-triangle-exclamation"></i>
                </div>
                <h2 style={{ color: "#f8fafc", fontSize: "20px" }}>No se pudo cargar el perfil</h2>
                <p style={{ color: "#94a3b8", marginBottom: "20px" }}>{error}</p>
                <button
                    onClick={() => window.location.reload()}
                    style={{
                        padding: "10px 20px",
                        background: "#0284c7",
                        color: "#fff",
                        border: "none",
                        borderRadius: "8px",
                        cursor: "pointer",
                        fontWeight: 600,
                    }}
                >
                    Reintentar
                </button>
            </div>
        );
    }

    // Inicial del avatar
    const initial = profile?.full_name ? profile.full_name.charAt(0).toUpperCase() : "A";

    return (
        <div className="profile-page">
            {/* DECORATIVE BLURS */}
            <div className="bg-blur bg-blur--one"></div>
            <div className="bg-blur bg-blur--two"></div>

            {/* PROFILE HEADER */}
            <section className="profile-header-card">
                <div className="profile-user">
                    <div className="avatar-container">
                        {profile?.avatar_url ? (
                            <img
                                src={profile.avatar_url}
                                alt={profile.full_name}
                                className="avatar-circle"
                                style={{ objectFit: "cover" }}
                            />
                        ) : (
                            <div className="avatar-circle">{initial}</div>
                        )}
                    </div>

                    <div className="profile-user__info">
                        <div className="name-row">
                            <h1>{profile?.full_name}</h1>
                            <Link to="/profile/edit">
                                <button className="edit-btn" title="Editar datos personales">
                                    <i className="fas fa-pen"></i>
                                </button>
                            </Link>
                        </div>

                        <p className="school-info">
                            {profile?.grade || "Grado 11°"} • Estudiante Oficial ATHENA
                        </p>

                        <div style={{ display: "flex", gap: "12px", marginTop: "6px", flexWrap: "wrap", fontSize: "13px", color: "#94a3b8" }}>
                            <span>
                                <i className="fas fa-id-card" style={{ marginRight: "5px", color: "#38bdf8" }}></i>
                                ID #{profile?.id}
                            </span>
                            <span>
                                <i className="fas fa-envelope" style={{ marginRight: "5px", color: "#38bdf8" }}></i>
                                {profile?.email}
                            </span>
                            <span>
                                <i className="fas fa-bullseye" style={{ marginRight: "5px", color: "#4ade80" }}></i>
                                Meta ICFES: {profile?.target_icfes_score || 350} pts
                            </span>
                        </div>
                    </div>
                </div>

                {/* QUICK STATS */}
                <div className="quick-stats">
                    <div className="quick-card">
                        <div className="quick-card__icon quick-card__icon--red">
                            <i className="fas fa-fire"></i>
                        </div>
                        <div className="quick-card__content">
                            <span className="quick-card__value">{profile?.streak_days || 0}</span>
                            <span className="quick-card__label">Racha</span>
                        </div>
                        <span className="quick-card__sub">días seguidos</span>
                    </div>

                    <div className="quick-card">
                        <div className="quick-card__icon quick-card__icon--purple">
                            <i className="fas fa-shield-halved"></i>
                        </div>
                        <div className="quick-card__content">
                            <span className="quick-card__label">Estado</span>
                            <span className="quick-card__value" style={{ fontSize: "16px" }}>{profile?.status || "Activo"}</span>
                        </div>
                        <span className="status-active">Verificado</span>
                    </div>
                </div>
            </section>

            {/* DETALLES INSTITUCIONALES */}
            <div className="section-heading" style={{ marginTop: "32px" }}>
                <h2>Información Institucional</h2>
                <p>Datos oficiales registrados en el sistema académico ATHENA.</p>
            </div>

            <section style={{
                display: "grid",
                gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))",
                gap: "16px",
                marginBottom: "32px",
            }}>
                <div style={{ background: "rgba(15, 23, 42, 0.7)", border: "1px solid rgba(148, 163, 184, 0.15)", borderRadius: "14px", padding: "18px" }}>
                    <span style={{ color: "#94a3b8", fontSize: "12px", display: "block" }}>Identificación Estudiantil</span>
                    <strong style={{ color: "#f8fafc", fontSize: "16px", display: "block", marginTop: "4px" }}>
                        ATHENA-EST-{String(profile?.id).padStart(5, "0")}
                    </strong>
                </div>

                <div style={{ background: "rgba(15, 23, 42, 0.7)", border: "1px solid rgba(148, 163, 184, 0.15)", borderRadius: "14px", padding: "18px" }}>
                    <span style={{ color: "#94a3b8", fontSize: "12px", display: "block" }}>Correo Institucional</span>
                    <strong style={{ color: "#38bdf8", fontSize: "15px", display: "block", marginTop: "4px", wordBreak: "break-all" }}>
                        {profile?.email}
                    </strong>
                </div>

                <div style={{ background: "rgba(15, 23, 42, 0.7)", border: "1px solid rgba(148, 163, 184, 0.15)", borderRadius: "14px", padding: "18px" }}>
                    <span style={{ color: "#94a3b8", fontSize: "12px", display: "block" }}>Teléfono de Contacto</span>
                    <strong style={{ color: "#f8fafc", fontSize: "15px", display: "block", marginTop: "4px" }}>
                        {profile?.phone || "No registrado"}
                    </strong>
                </div>

                <div style={{ background: "rgba(15, 23, 42, 0.7)", border: "1px solid rgba(148, 163, 184, 0.15)", borderRadius: "14px", padding: "18px" }}>
                    <span style={{ color: "#94a3b8", fontSize: "12px", display: "block" }}>Fecha de Registro</span>
                    <strong style={{ color: "#f8fafc", fontSize: "15px", display: "block", marginTop: "4px" }}>
                        {profile?.created_at ? new Date(profile.created_at).toLocaleDateString("es-CO", { year: "numeric", month: "long", day: "numeric" }) : "Agosto 2026"}
                    </strong>
                </div>
            </section>

            {/* SECCIÓN CUENTA */}
            <div className="section-heading">
                <h2>Accesos Rápidos</h2>
                <p>Gestiona tu progreso, reconocimientos y entrenamientos.</p>
            </div>

            {/* ACCOUNT GRID */}
            <section className="account-grid">
                <Link to="/profile/achievements">
                    <article className="action-card">
                        <div className="action-card__icon blue-bg">
                            <i className="fas fa-award"></i>
                        </div>
                        <div className="action-card__content">
                            <h3>Mis Logros</h3>
                            <p>Descubre tus medallas y reconocimientos académicos desbloqueados.</p>
                            <span className="action-card__badge blue">
                                {achievementsSummary
                                    ? `${achievementsSummary.unlocked_count} / ${achievementsSummary.total_count} desbloqueados`
                                    : "Ver medallas"}
                            </span>
                        </div>
                        <i className="fas fa-chevron-right action-card__arrow"></i>
                    </article>
                </Link>

                <Link to="/statistics">
                    <article className="action-card">
                        <div className="action-card__icon purple-bg">
                            <i className="fas fa-chart-pie"></i>
                        </div>
                        <div className="action-card__content">
                            <h3>Estadísticas y Progreso</h3>
                            <p>Monitorea tu evolución en preguntas reales ICFES.</p>
                            <span className="action-card__badge purple">
                                En tiempo real
                            </span>
                        </div>
                        <i className="fas fa-chevron-right action-card__arrow"></i>
                    </article>
                </Link>

                <Link to="/suscripciones">
                    <article className="action-card">
                        <div className="action-card__icon pink-bg">
                            <i className="fas fa-crown"></i>
                        </div>
                        <div className="action-card__content">
                            <h3>Planes y Suscripciones</h3>
                            <p>Gestiona tu plan ATHENA Premium y pagos con Wompi.</p>
                            <span className="action-card__badge pink">
                                {activeSubscription
                                    ? `Plan Activo: ${activeSubscription.plan_name || "Premium"}`
                                    : "Wompi / Premium"}
                            </span>
                        </div>
                        <i className="fas fa-chevron-right action-card__arrow"></i>
                    </article>
                </Link>

                <Link to="/profile/notifications">
                    <article className="action-card">
                        <div className="action-card__icon green-bg">
                            <i className="fas fa-bell"></i>
                        </div>
                        <div className="action-card__content">
                            <h3>Bandeja de Notificaciones</h3>
                            <p>Revisa avisos académicos, recordatorios y logros.</p>
                            <span className="action-card__badge green">
                                Actualizaciones
                            </span>
                        </div>
                        <i className="fas fa-chevron-right action-card__arrow"></i>
                    </article>
                </Link>
            </section>

            {/* FOOTER CON BOTÓN DE CERRAR SESIÓN */}
            <footer className="profile-footer" style={{ marginTop: "40px" }}>
                <button
                    onClick={handleLogout}
                    className="logout-btn"
                    style={{
                        background: "rgba(239, 68, 68, 0.15)",
                        border: "1px solid rgba(239, 68, 68, 0.35)",
                        color: "#f87171",
                        padding: "10px 20px",
                        borderRadius: "10px",
                        cursor: "pointer",
                        fontSize: "14px",
                        fontWeight: 600,
                        display: "inline-flex",
                        alignItems: "center",
                        gap: "8px",
                    }}
                >
                    <i className="fas fa-sign-out-alt"></i>
                    Cerrar sesión institucional
                </button>

                <div className="footer-help">
                    ¿Tienes dudas sobre tu cuenta?{" "}
                    <Link to="/profile/edit">
                        Editar perfil o clave
                        <i className="fas fa-arrow-right" style={{ marginLeft: "6px" }}></i>
                    </Link>
                </div>
            </footer>
        </div>
    );
};

export default Profile;
