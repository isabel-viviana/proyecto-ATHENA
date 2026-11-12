import "./profile.css";
import { Link, useNavigate } from "react-router-dom";
import { useState, useEffect } from "react";
import { useAuthStore } from "../../stores/useAuthStore";
import { getStudentProfileData } from "../../api/studentQuestions";

const Profile = () => {
    const navigate = useNavigate();
    const user = useAuthStore((state) => state.user);
    const logout = useAuthStore((state) => state.logout);
    const [profile, setProfile] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");

    useEffect(() => {
        const fetchProfile = async () => {
            try {
                setLoading(true);
                const res = await getStudentProfileData();
                if (res && res.success && res.data) {
                    setProfile(res.data);
                } else if (user) {
                    setProfile(user);
                } else {
                    setError(res?.message || "No se pudo obtener el perfil.");
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
            localStorage.removeItem("athena_token");
            localStorage.removeItem("athena_student");
            await logout();
        } catch {}
        navigate("/login");
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

    // Cálculos seguros de progreso de XP
    const userLevel = profile?.level || 1;
    const currentXp = profile?.xp || 0;
    const targetXpForLevel = userLevel * 1000;
    const xpPercent = Math.min(100, Math.round((currentXp / targetXpForLevel) * 100));

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
                        <span className="level-badge">LVL {userLevel}</span>
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

                        {/* XP */}
                        <div className="xp-container" style={{ marginTop: "16px" }}>
                            <div className="xp-label">
                                <span>
                                    Nivel <strong>{userLevel}</strong>
                                </span>
                                <span>
                                    <strong>{xpPercent}%</strong> para el siguiente nivel
                                </span>
                            </div>

                            <div className="xp-bar-bg">
                                <div className="xp-bar-fill" style={{ width: `${xpPercent}%` }}></div>
                            </div>

                            <p className="xp-count">
                                {currentXp.toLocaleString()} / {targetXpForLevel.toLocaleString()} XP
                            </p>
                        </div>
                    </div>
                </div>

                {/* QUICK STATS */}
                <div className="quick-stats">
                    <div className="quick-card">
                        <div className="quick-card__icon quick-card__icon--gold">
                            <i className="fas fa-coins"></i>
                        </div>
                        <div className="quick-card__content">
                            <span className="quick-card__value">{(profile?.coins || 0).toLocaleString()}</span>
                            <span className="quick-card__label">Monedas</span>
                        </div>
                        <Link to="/store" className="quick-card__link">
                            Ver tienda
                        </Link>
                    </div>

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
                            <p>Descubre tus medallas y reconocimientos desbloqueados.</p>
                            <span className="action-card__badge blue">
                                4 disponibles
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

                <Link to="/ranking">
                    <article className="action-card">
                        <div className="action-card__icon pink-bg">
                            <i className="fas fa-trophy"></i>
                        </div>
                        <div className="action-card__content">
                            <h3>Ranking Institucional</h3>
                            <p>Consulta tu posición en la tabla general de líderes.</p>
                            <span className="action-card__badge pink">
                                Clasificación oficial
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
