import "./mock_results.css";
import { useEffect, useState } from "react";
import { Link, useNavigate, useSearchParams } from "react-router-dom";

import { getExamResults } from "../../api/exams";

const BAR_COLORS = ["blue", "green", "yellow", "red", "purple"];

const formatTime = (seconds) => {
    if (!seconds || seconds <= 0) return "0 seg";
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    if (mins === 0) return `${secs} seg`;
    if (secs === 0) return `${mins} min`;
    return `${mins} min ${secs} seg`;
};

const buildDonutSegments = (correct, incorrect, omitted, total) => {
    if (!total || total === 0) {
        return {
            correctDash: "0 100",
            incorrectDash: "0 100",
            incorrectOffset: "0",
            omittedDash: "0 100",
            omittedOffset: "0",
        };
    }

    const correctPct = Math.round((correct / total) * 100);
    const incorrectPct = Math.round((incorrect / total) * 100);
    const omittedPct = Math.max(0, 100 - correctPct - incorrectPct);

    return {
        correctDash: `${correctPct} ${100 - correctPct}`,
        incorrectDash: `${incorrectPct} ${100 - incorrectPct}`,
        incorrectOffset: `${-correctPct}`,
        omittedDash: `${omittedPct} ${100 - omittedPct}`,
        omittedOffset: `${-(correctPct + incorrectPct)}`,
    };
};

const normalizeResults = (raw) => {
    const root = raw?.data ?? raw;
    const attempt = root?.attempt ?? root;

    const correct = attempt?.correct_answers ?? attempt?.correct ?? 0;
    const incorrect = attempt?.incorrect_answers ?? attempt?.incorrect ?? 0;
    const omitted = attempt?.omitted_answers ?? attempt?.omitted ?? 0;
    const total = attempt?.questions_count ?? attempt?.total_questions ?? (correct + incorrect + omitted);

    const score = typeof attempt?.score === "number" ? attempt.score : 0;
    const accuracy = typeof attempt?.accuracy === "number"
        ? Math.round(attempt.accuracy)
        : (total > 0 ? Math.round((correct / total) * 100) : 0);

    const title = attempt?.title ?? "Simulacro ICFES";
    const subjectName = attempt?.subject_name ?? "Lectura Crítica";
    const totalTime = attempt?.total_time_seconds ?? 0;
    const finishedAt = attempt?.finished_at ?? null;

    let subjects = [];
    if (Array.isArray(attempt?.subject_performance) && attempt.subject_performance.length > 0) {
        subjects = attempt.subject_performance.map((s, i) => ({
            name: s.subject_name || s.name || `Área ${i + 1}`,
            score: s.correct ?? 0,
            total: s.total ?? 0,
            percent: s.percent ?? (s.total > 0 ? Math.round((s.correct / s.total) * 100) : 0),
            color: BAR_COLORS[i % BAR_COLORS.length],
        }));
    } else {
        subjects = [
            {
                name: subjectName,
                score: correct,
                total: total,
                percent: accuracy,
                color: "blue",
            },
        ];
    }

    return {
        score,
        correct,
        incorrect,
        omitted,
        total,
        accuracy,
        title,
        subjectName,
        totalTime,
        finishedAt,
        subjects,
    };
};

const MockResults = () => {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();

    const examId = searchParams.get("id");
    const isExpired = searchParams.get("expired") === "true";

    const [results, setResults] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");

    useEffect(() => {
        const loadResults = async () => {
            if (!examId) {
                setError("No encontramos el ID del simulacro. Regresa a configuración o a tu historial.");
                setLoading(false);
                return;
            }

            try {
                setLoading(true);
                setError("");

                const raw = await getExamResults(examId);
                console.log("RESULTADOS DEL SIMULACRO RECIBIDOS:", raw);

                const normalized = normalizeResults(raw);
                setResults(normalized);
            } catch (err) {
                console.error("ERROR AL CARGAR RESULTADOS:", err);
                setError(
                    err?.response?.data?.message ??
                        err?.message ??
                        "No se pudieron cargar los resultados del simulacro."
                );
            } finally {
                setLoading(false);
            }
        };

        loadResults();
    }, [examId]);

    if (loading) {
        return (
            <div className="main__content">
                <section className="result-card" style={{ padding: "60px 20px" }}>
                    <div style={{ fontSize: "36px", color: "#38bdf8", marginBottom: "16px" }}>
                        <i className="fas fa-spinner fa-spin"></i>
                    </div>
                    <h1 style={{ fontSize: "22px" }}>Calculando resultados oficiales...</h1>
                    <p style={{ color: "#94a3b8" }}>
                        Verificando respuestas contra la clave oficial ICFES y generando informe.
                    </p>
                </section>
            </div>
        );
    }

    if (error) {
        return (
            <div className="main__content">
                <section className="result-card" style={{ padding: "50px 20px" }}>
                    <div style={{ fontSize: "36px", color: "#ef4444", marginBottom: "16px" }}>
                        <i className="fas fa-triangle-exclamation"></i>
                    </div>
                    <h1 style={{ fontSize: "22px" }}>No pudimos cargar los resultados</h1>
                    <p style={{ color: "#94a3b8", marginBottom: "24px" }}>{error}</p>
                    <div className="actions" style={{ justifyContent: "center" }}>
                        <button
                            type="button"
                            className="btn btn--secondary"
                            onClick={() => navigate("/mock/history")}
                        >
                            Ver Mi Historial
                        </button>
                        <button
                            type="button"
                            className="btn btn--primary"
                            onClick={() => navigate("/mock/config")}
                        >
                            Presentar Nuevo Simulacro
                        </button>
                    </div>
                </section>
            </div>
        );
    }

    const {
        score,
        correct,
        incorrect,
        omitted,
        total,
        accuracy,
        title,
        subjectName,
        totalTime,
        subjects,
    } = results;

    const {
        correctDash,
        incorrectDash,
        incorrectOffset,
        omittedDash,
        omittedOffset,
    } = buildDonutSegments(correct, incorrect, omitted, total);

    return (
        <div className="main__content" style={{ maxWidth: "980px", margin: "0 auto", padding: "30px 24px" }}>
            {/* AVISO DE TIEMPO EXPIRADO SI APLICA */}
            {isExpired && (
                <div style={{
                    backgroundColor: "rgba(245, 158, 11, 0.15)",
                    border: "1px solid rgba(245, 158, 11, 0.4)",
                    borderRadius: "12px",
                    padding: "14px 18px",
                    marginBottom: "20px",
                    color: "#fbbf24",
                    fontSize: "14px",
                    display: "flex",
                    alignItems: "center",
                    gap: "10px",
                }}>
                    <i className="fas fa-hourglass-end" style={{ fontSize: "18px" }}></i>
                    <div>
                        <strong>El tiempo límite ha concluido.</strong> Tu simulacro fue calificado automáticamente con las respuestas guardadas hasta ese momento.
                    </div>
                </div>
            )}

            {/* RESULTADO GLOBAL CARD */}
            <section className="result-card">
                <span style={{
                    display: "inline-block",
                    backgroundColor: "rgba(56, 189, 248, 0.15)",
                    border: "1px solid rgba(56, 189, 248, 0.35)",
                    color: "#38bdf8",
                    padding: "4px 12px",
                    borderRadius: "999px",
                    fontSize: "12px",
                    fontWeight: 700,
                    letterSpacing: "0.5px",
                    marginBottom: "10px",
                }}>
                    {title}
                </span>

                <h1 style={{ fontSize: "32px", fontWeight: 800, margin: "6px 0" }}>
                    ¡Simulacro Finalizado!
                </h1>
                <p style={{ color: "#94a3b8", fontSize: "15px", marginBottom: "26px" }}>
                    Evaluación estructurada sobre reactivos oficiales de {subjectName}.
                </p>

                <div className="score-circle">
                    <div className="score-circle__inner">
                        <h2 style={{ fontSize: "36px", fontWeight: 800, margin: 0 }}>{score}</h2>
                        <span style={{ fontSize: "12px", color: "#94a3b8", textTransform: "uppercase", letterSpacing: "0.5px" }}>
                            Puntaje ICFES
                        </span>
                    </div>
                </div>

                {/* MINI-STATS */}
                <div className="result-mini-stats" style={{ marginTop: "28px" }}>
                    <div className="result-mini-stat result-mini-stat--green">
                        <span className="result-mini-stat__value">{correct}</span>
                        <span className="result-mini-stat__label">Correctas</span>
                    </div>
                    <div className="result-mini-stat result-mini-stat--red">
                        <span className="result-mini-stat__value">{incorrect}</span>
                        <span className="result-mini-stat__label">Incorrectas</span>
                    </div>
                    <div className="result-mini-stat result-mini-stat--gray">
                        <span className="result-mini-stat__value">{omitted}</span>
                        <span className="result-mini-stat__label">Omitidas</span>
                    </div>
                    <div className="result-mini-stat result-mini-stat--blue">
                        <span className="result-mini-stat__value">{accuracy}%</span>
                        <span className="result-mini-stat__label">Precisión</span>
                    </div>
                    <div className="result-mini-stat" style={{ borderLeft: "3px solid #64748b" }}>
                        <span className="result-mini-stat__value">{formatTime(totalTime)}</span>
                        <span className="result-mini-stat__label">Tiempo Total</span>
                    </div>
                </div>
            </section>

            {/* STATS GRID: DONUT & ÁREA PERFORMANCE */}
            <section className="stats-grid">
                {/* DONUT DESEMPEÑO GENERAL */}
                <div className="card">
                    <h3>
                        <i className="fas fa-chart-pie" style={{ color: "#38bdf8", marginRight: "8px" }}></i>
                        Desempeño General
                    </h3>

                    <div className="donut-chart" style={{ margin: "20px auto" }}>
                        <svg viewBox="0 0 42 42">
                            {/* Fondo */}
                            <circle
                                cx="21"
                                cy="21"
                                r="15.915"
                                fill="transparent"
                                stroke="#1E293B"
                                strokeWidth="3"
                            />
                            {/* Correctas */}
                            <circle
                                cx="21"
                                cy="21"
                                r="15.915"
                                fill="transparent"
                                stroke="#22C55E"
                                strokeWidth="3"
                                strokeDasharray={correctDash}
                                strokeLinecap="round"
                                transform="rotate(-90 21 21)"
                            />
                            {/* Incorrectas */}
                            <circle
                                cx="21"
                                cy="21"
                                r="15.915"
                                fill="transparent"
                                stroke="#EF4444"
                                strokeWidth="3"
                                strokeDasharray={incorrectDash}
                                strokeDashoffset={incorrectOffset}
                                strokeLinecap="round"
                                transform="rotate(-90 21 21)"
                            />
                            {/* Omitidas */}
                            <circle
                                cx="21"
                                cy="21"
                                r="15.915"
                                fill="transparent"
                                stroke="#94A3B8"
                                strokeWidth="3"
                                strokeDasharray={omittedDash}
                                strokeDashoffset={omittedOffset}
                                strokeLinecap="round"
                                transform="rotate(-90 21 21)"
                            />
                        </svg>
                    </div>

                    <div className="legend">
                        <div className="legend-item">
                            <span className="dot dot--green"></span>
                            Correctas ({correct})
                        </div>
                        <div className="legend-item">
                            <span className="dot dot--red"></span>
                            Incorrectas ({incorrect})
                        </div>
                        <div className="legend-item">
                            <span className="dot dot--gray"></span>
                            Omitidas ({omitted})
                        </div>
                    </div>
                </div>

                {/* RENDIMIENTO POR ÁREA */}
                <div className="card">
                    <h3>
                        <i className="fas fa-book-open" style={{ color: "#38bdf8", marginRight: "8px" }}></i>
                        Resultado por Competencia / Área
                    </h3>

                    {subjects.map((subj, idx) => {
                        const widthPct = Math.min(100, Math.max(0, subj.percent));
                        return (
                            <div className="subject" key={idx} style={{ marginTop: "18px" }}>
                                <div className="subject__top" style={{ display: "flex", justifyContent: "space-between", marginBottom: "6px", fontSize: "14px", fontWeight: 600 }}>
                                    <span>{subj.name}</span>
                                    <span style={{ color: "#38bdf8" }}>
                                        {subj.score} de {subj.total} ({subj.percent}%)
                                    </span>
                                </div>
                                <div className="subject__bar">
                                    <div
                                        className={`subject__fill ${subj.color || "blue"}`}
                                        style={{ width: `${widthPct}%` }}
                                    ></div>
                                </div>
                            </div>
                        );
                    })}

                    <div style={{
                        marginTop: "24px",
                        padding: "12px",
                        backgroundColor: "rgba(15, 23, 42, 0.5)",
                        borderRadius: "10px",
                        border: "1px solid rgba(148, 163, 184, 0.15)",
                        fontSize: "13px",
                        color: "#94a3b8",
                        lineHeight: "1.5",
                    }}>
                        <i className="fas fa-circle-check" style={{ color: "#22c55e", marginRight: "6px" }}></i>
                        Tus respuestas han sido sincronizadas en tu perfil y estadísticas históricas.
                    </div>
                </div>
            </section>

            {/* BOTONES DE ACCIÓN */}
            <section className="actions" style={{ marginTop: "32px", display: "flex", flexWrap: "wrap", gap: "12px", justifyContent: "center" }}>
                <Link to={`/mock/review?id=${examId}`}>
                    <button type="button" className="btn btn--secondary" style={{ display: "inline-flex", alignItems: "center", gap: "8px" }}>
                        <i className="fas fa-list-check"></i>
                        Revisar Preguntas Detalladas
                    </button>
                </Link>

                <Link to="/mock/history">
                    <button type="button" className="btn btn--secondary" style={{ display: "inline-flex", alignItems: "center", gap: "8px" }}>
                        <i className="fas fa-clock-rotate-left"></i>
                        Ver Mi Historial
                    </button>
                </Link>

                <Link to="/mock/config">
                    <button type="button" className="btn btn--primary" style={{ display: "inline-flex", alignItems: "center", gap: "8px" }}>
                        <i className="fas fa-rotate-right"></i>
                        Presentar Nuevo Simulacro
                    </button>
                </Link>

                <Link to="/dashboard">
                    <button type="button" className="btn btn--ghost" style={{ display: "inline-flex", alignItems: "center", gap: "8px" }}>
                        <i className="fas fa-house"></i>
                        Dashboard
                    </button>
                </Link>
            </section>
        </div>
    );
};

export default MockResults;
