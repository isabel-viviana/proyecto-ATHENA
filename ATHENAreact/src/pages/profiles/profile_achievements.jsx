import "./profile_achievements.css";
import { useState, useEffect } from "react";
import { Link } from "react-router-dom";
import { getStudentAchievements } from "../../api/studentQuestions";

const CATEGORY_STYLES = {
    "Constancia": {
        bg: "rgba(248, 113, 113, 0.14)",
        border: "rgba(248, 113, 113, 0.35)",
        color: "#f87171",
    },
    "Práctica": {
        bg: "rgba(56, 189, 248, 0.14)",
        border: "rgba(56, 189, 248, 0.35)",
        color: "#38bdf8",
    },
    "Precisión": {
        bg: "rgba(74, 222, 128, 0.14)",
        border: "rgba(74, 222, 128, 0.35)",
        color: "#4ade80",
    },
    "Dominio ICFES": {
        bg: "rgba(167, 139, 250, 0.14)",
        border: "rgba(167, 139, 250, 0.35)",
        color: "#a78bfa",
    },
};

const ProfileAchievements = () => {
    const [achievements, setAchievements] = useState([]);
    const [summary, setSummary] = useState({
        unlocked_count: 0,
        total_count: 0,
        completion_percent: 0,
        total_answered: 0,
        streak_days: 0,
        accuracy: 0,
    });
    const [filter, setFilter] = useState("all"); // all | unlocked | in_progress
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");

    const fetchAchievements = async () => {
        try {
            setLoading(true);
            setError("");
            const res = await getStudentAchievements();
            if (res && res.success && res.data) {
                setAchievements(res.data.achievements || []);
                if (res.data.summary) {
                    setSummary(res.data.summary);
                }
            } else {
                setError(res?.message || "No se pudieron cargar tus logros académicos.");
            }
        } catch (err) {
            console.error("Error al cargar logros:", err);
            setError(
                err?.response?.data?.message ||
                    "Error al conectar con el servidor de logros académicos."
            );
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchAchievements();
    }, []);

    const filteredAchievements = achievements.filter((item) => {
        if (filter === "unlocked") return Boolean(item.unlocked);
        if (filter === "in_progress") return !item.unlocked;
        return true;
    });

    const unlockedCount = Number(summary.unlocked_count || 0);
    const totalCount = Number(summary.total_count || achievements.length || 0);
    const inProgressCount = Math.max(0, totalCount - unlockedCount);
    const completionPercent = Number(summary.completion_percent || 0);

    return (
        <div className="achievements-page">
            <div className="achievements-blur achievements-blur--one"></div>
            <div className="achievements-blur achievements-blur--two"></div>

            {/* TOP BAR & BACK BUTTON */}
            <div className="achievements-topbar">
                <Link to="/profile" className="achievements-back-btn">
                    <i className="fas fa-arrow-left"></i>
                    <span>Volver a Mi Perfil</span>
                </Link>

                <button
                    type="button"
                    onClick={fetchAchievements}
                    className="achievements-refresh-btn"
                    disabled={loading}
                >
                    <i className={`fas fa-arrows-rotate ${loading ? "fa-spin" : ""}`}></i>
                    <span>Actualizar progreso</span>
                </button>
            </div>

            {/* HERO SUMMARY CARD */}
            <section className="achievements-hero-card">
                <div className="achievements-hero__info">
                    <span className="achievements-hero__tag">
                        <i className="fas fa-award"></i> Reconocimientos Académicos ICFES
                    </span>
                    <h1>Mis Logros y Medallas</h1>
                    <p>
                        Cada logro refleja tu disciplina real resolviendo reactivos del banco oficial
                        ICFES, tu efectividad académica y tu racha de días consecutivos de estudio.
                    </p>

                    <div className="achievements-hero__metrics">
                        <div className="achievements-metric-pill">
                            <i className="fas fa-check-double" style={{ color: "#38bdf8" }}></i>
                            <span>
                                <strong>{summary.total_answered || 0}</strong> respuestas registradas
                            </span>
                        </div>
                        <div className="achievements-metric-pill">
                            <i className="fas fa-fire" style={{ color: "#f87171" }}></i>
                            <span>
                                <strong>{summary.streak_days || 0}</strong> días de racha
                            </span>
                        </div>
                        <div className="achievements-metric-pill">
                            <i className="fas fa-bullseye" style={{ color: "#4ade80" }}></i>
                            <span>
                                <strong>{summary.accuracy || 0}%</strong> de efectividad
                            </span>
                        </div>
                    </div>
                </div>

                <div className="achievements-hero__progress-box">
                    <div className="achievements-hero__count-row">
                        <div>
                            <span className="achievements-hero__count-label">Logros Desbloqueados</span>
                            <div className="achievements-hero__count-value">
                                {unlockedCount} <span>/ {totalCount}</span>
                            </div>
                        </div>
                        <div className="achievements-hero__percent-badge">
                            {completionPercent}%
                        </div>
                    </div>

                    <div className="achievements-progress-track">
                        <div
                            className="achievements-progress-fill"
                            style={{ width: `${completionPercent}%` }}
                        ></div>
                    </div>

                    <p className="achievements-hero__progress-note">
                        {unlockedCount === totalCount && totalCount > 0
                            ? "¡Felicitaciones! Has desbloqueado la totalidad de logros académicos."
                            : `Te faltan ${inProgressCount} ${
                                  inProgressCount === 1 ? "logro" : "logros"
                              } por desbloquear. ¡Continúa practicando!`}
                    </p>
                </div>
            </section>

            {/* FILTER TABS */}
            <div className="achievements-filters">
                <button
                    type="button"
                    className={`achievements-filter-btn ${filter === "all" ? "active" : ""}`}
                    onClick={() => setFilter("all")}
                >
                    <span>Todos</span>
                    <span className="filter-count">{totalCount}</span>
                </button>
                <button
                    type="button"
                    className={`achievements-filter-btn ${filter === "unlocked" ? "active" : ""}`}
                    onClick={() => setFilter("unlocked")}
                >
                    <i className="fas fa-unlock"></i>
                    <span>Desbloqueados</span>
                    <span className="filter-count">{unlockedCount}</span>
                </button>
                <button
                    type="button"
                    className={`achievements-filter-btn ${filter === "in_progress" ? "active" : ""}`}
                    onClick={() => setFilter("in_progress")}
                >
                    <i className="fas fa-hourglass-half"></i>
                    <span>En progreso</span>
                    <span className="filter-count">{inProgressCount}</span>
                </button>
            </div>

            {/* LOADING STATE */}
            {loading && (
                <div className="achievements-state-box">
                    <div className="achievements-spinner">
                        <i className="fas fa-spinner fa-spin"></i>
                    </div>
                    <h3>Evaluando tus logros académicos...</h3>
                    <p>Consultando tus respuestas reales y racha de estudio en ATHENA.</p>
                </div>
            )}

            {/* ERROR STATE */}
            {!loading && error && (
                <div className="achievements-error-box">
                    <i className="fas fa-triangle-exclamation"></i>
                    <div>
                        <h4>No fue posible cargar los logros</h4>
                        <p>{error}</p>
                    </div>
                    <button type="button" onClick={fetchAchievements}>
                        Reintentar
                    </button>
                </div>
            )}

            {/* ACHIEVEMENTS GRID */}
            {!loading && !error && (
                <>
                    {filteredAchievements.length === 0 ? (
                        <div className="achievements-state-box">
                            <div className="achievements-empty-icon">
                                <i className="fas fa-medal"></i>
                            </div>
                            <h3>
                                {filter === "unlocked"
                                    ? "Aún no tienes logros desbloqueados"
                                    : "No hay logros en esta categoría"}
                            </h3>
                            <p>
                                {filter === "unlocked"
                                    ? "Resuelve tu primera pregunta en Prácticas o inicia un Simulacro para desbloquear tu primera medalla."
                                    : "Todos los logros disponibles ya fueron completados."}
                            </p>
                            <Link to="/practices" className="achievements-cta-btn">
                                <i className="fas fa-dumbbell"></i>
                                <span>Ir a Prácticas ICFES</span>
                            </Link>
                        </div>
                    ) : (
                        <section className="achievements-grid">
                            {filteredAchievements.map((ach) => {
                                const isUnlocked = Boolean(ach.unlocked);
                                const catStyle = CATEGORY_STYLES[ach.category] || {
                                    bg: "rgba(56, 189, 248, 0.14)",
                                    border: "rgba(56, 189, 248, 0.35)",
                                    color: "#38bdf8",
                                };
                                const pct = Number(ach.progress_percent || 0);

                                return (
                                    <article
                                        key={ach.id}
                                        className={`achievement-card ${
                                            isUnlocked
                                                ? "achievement-card--unlocked"
                                                : "achievement-card--locked"
                                        }`}
                                    >
                                        <div className="achievement-card__top">
                                            <div
                                                className="achievement-card__icon"
                                                style={
                                                    isUnlocked
                                                        ? {
                                                              background: catStyle.bg,
                                                              borderColor: catStyle.border,
                                                              color: catStyle.color,
                                                          }
                                                        : undefined
                                                }
                                            >
                                                <i className={`fas ${ach.icon || "fa-award"}`}></i>
                                            </div>

                                            <div className="achievement-card__badges">
                                                <span
                                                    className="achievement-category-tag"
                                                    style={{
                                                        background: catStyle.bg,
                                                        borderColor: catStyle.border,
                                                        color: catStyle.color,
                                                    }}
                                                >
                                                    {ach.category}
                                                </span>

                                                <span
                                                    className={`achievement-status-tag ${
                                                        isUnlocked ? "unlocked" : "locked"
                                                    }`}
                                                >
                                                    <i
                                                        className={`fas ${
                                                            isUnlocked ? "fa-circle-check" : "fa-lock"
                                                        }`}
                                                    ></i>
                                                    {isUnlocked ? "Desbloqueado" : "En progreso"}
                                                </span>
                                            </div>
                                        </div>

                                        <div className="achievement-card__body">
                                            <h3>{ach.title}</h3>
                                            <p>{ach.description}</p>
                                        </div>

                                        <div className="achievement-card__footer">
                                            <div className="achievement-progress-meta">
                                                <span>
                                                    Progreso:{" "}
                                                    <strong>
                                                        {Math.min(ach.current, ach.target)} / {ach.target}
                                                    </strong>{" "}
                                                    {ach.unit}
                                                </span>
                                                <span className="achievement-progress-pct">{pct}%</span>
                                            </div>

                                            <div className="achievement-bar-track">
                                                <div
                                                    className={`achievement-bar-fill ${
                                                        isUnlocked ? "unlocked" : ""
                                                    }`}
                                                    style={{ width: `${pct}%` }}
                                                ></div>
                                            </div>
                                        </div>
                                    </article>
                                );
                            })}
                        </section>
                    )}
                </>
            )}
        </div>
    );
};

export default ProfileAchievements;
