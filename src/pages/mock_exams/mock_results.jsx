import "./mock_results.css";
import { useEffect, useState } from "react";
import { Link, useNavigate, useSearchParams } from "react-router-dom";

import { getExamResults } from "../../api/exams";

/*
 * Colores de barra por índice de materia.
 * Si el backend envía un color, se usa ese.
 * Si no, se rota por esta paleta.
 */
const BAR_COLORS = ["green", "blue", "yellow", "red", "green"];

/*
 * Calcula stroke-dasharray / stroke-dashoffset para el donut SVG.
 *
 * El círculo tiene circumference = 2 * π * 15.915 ≈ 100
 * → 1 punto de dash = 1 %
 */
const buildDonutSegments = (correct, incorrect, omitted, total) => {
    if (!total || total === 0) {
        return { correctDash: "0 100", incorrectDash: "0 100", incorrectOffset: "0", omittedDash: "0 100", omittedOffset: "0" };
    }

    const correctPct   = Math.round((correct  / total) * 100);
    const incorrectPct = Math.round((incorrect / total) * 100);
    const omittedPct   = Math.max(0, 100 - correctPct - incorrectPct);

    return {
        correctDash:    `${correctPct} ${100 - correctPct}`,
        incorrectDash:  `${incorrectPct} ${100 - incorrectPct}`,
        incorrectOffset: `${-correctPct}`,
        omittedDash:    `${omittedPct} ${100 - omittedPct}`,
        omittedOffset:  `${-(correctPct + incorrectPct)}`,
    };
};

/*
 * Adapta la respuesta de Laravel a una forma canónica.
 * El backend puede devolver distintas shapes.
 */
const normalizeResults = (raw) => {
    // Intentamos varias capas de wrapping que Laravel puede enviar
    const data = raw?.data ?? raw;

    const correct   = data?.correct_answers   ?? data?.correct   ?? data?.correctas   ?? data?.correct_count ?? 0;
    const incorrect = data?.incorrect_answers ?? data?.incorrect ?? data?.incorrectas ?? data?.incorrect_count ?? 0;
    const omitted   = data?.omitted_answers   ?? data?.omitted   ?? data?.omitidas    ?? data?.omitted_count ?? 0;
    const total     = data?.total_questions   ?? data?.total     ?? (correct + incorrect + omitted);

    // Semántica real: 'score_percentage' es el porcentaje de aciertos calculado por Laravel (0 - 100)
    const score     = data?.score_percentage  ?? data?.score     ?? data?.total_score ?? data?.puntaje ?? 0;
    const accuracy  = data?.score_percentage  ?? data?.accuracy  ?? data?.porcentaje_aciertos
                   ?? (total > 0 ? Math.round((correct / total) * 100) : 0);

    // Rendimiento por materia — estructura real de Laravel: subject_performance
    const rawSubjects =
        data?.subject_performance  ??
        data?.subjects_performance ??
        data?.subjects             ??
        data?.areas                ??
        [];

    const subjects = rawSubjects.map((s, i) => ({
        name    : s?.subject_name ?? s?.name ?? s?.subject ?? s?.materia ?? `Área ${i + 1}`,
        score   : s?.correct      ?? s?.score ?? s?.puntaje ?? 0,
        total   : s?.total        ?? s?.questions ?? s?.total_questions ?? 0,
        percent : s?.score_percentage ?? s?.percent ?? s?.accuracy ??
                  (s?.total > 0 ? Math.round(((s?.correct ?? s?.score ?? 0) / s?.total) * 100) : 0),
        color   : s?.color        ?? BAR_COLORS[i % BAR_COLORS.length],
    }));

    return { score, correct, incorrect, omitted, total, accuracy, subjects };
};

/* ============================================================
   COMPONENTE PRINCIPAL
   ============================================================ */

const MockResults = () => {
    const [searchParams]  = useSearchParams();
    const navigate        = useNavigate();

    const examId = searchParams.get("id");

    const [results, setResults] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error,   setError  ] = useState("");

    /* ----------------------------------------------------------
       CARGAR RESULTADOS
    ---------------------------------------------------------- */
    useEffect(() => {
        const loadResults = async () => {
            if (!examId) {
                setError("No encontramos el ID del simulacro. Regresa al dashboard e inténtalo nuevamente.");
                setLoading(false);
                return;
            }

            try {
                setLoading(true);
                setError("");

                const raw = await getExamResults(examId);

                console.log("RESULTADOS DEL SIMULACRO:", raw);

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

    /* ----------------------------------------------------------
       ESTADO DE CARGA
    ---------------------------------------------------------- */
    if (loading) {
        return (
            <div className="main__content">
                <section className="result-card">
                    <h1 className="result-loading__title">Cargando resultados...</h1>
                    <p>Estamos calculando tu desempeño en el simulacro.</p>
                    <div className="result-loading__spinner"></div>
                </section>
            </div>
        );
    }

    /* ----------------------------------------------------------
       ESTADO DE ERROR
    ---------------------------------------------------------- */
    if (error) {
        return (
            <div className="main__content">
                <section className="result-card">
                    <h1>No pudimos cargar los resultados</h1>
                    <p>{error}</p>

                    <div className="actions" style={{ marginTop: "28px" }}>
                        <button
                            className="btn btn--secondary"
                            onClick={() => navigate("/dashboard")}
                        >
                            Volver al Dashboard
                        </button>
                    </div>
                </section>
            </div>
        );
    }

    /* ----------------------------------------------------------
       DATOS CALCULADOS
    ---------------------------------------------------------- */
    const { score, correct, incorrect, omitted, total, accuracy, subjects } = results;

    const { correctDash, incorrectDash, incorrectOffset, omittedDash, omittedOffset } =
        buildDonutSegments(correct, incorrect, omitted, total);

    /* ----------------------------------------------------------
       INTERFAZ
    ---------------------------------------------------------- */
    return (
        <>
            <div className="main__content">

                {/* RESULTADO GLOBAL */}
                <section className="result-card">
                    <h1>¡Simulacro Finalizado!</h1>
                    <p>Aquí están tus resultados globales.</p>

                    <div className="score-circle">
                        <div className="score-circle__inner">
                            <h2>{score}%</h2>
                            <span>Rendimiento Global</span>
                        </div>
                    </div>

                    {/* Mini-stats debajo del círculo */}
                    <div className="result-mini-stats">
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
                            <span className="result-mini-stat__label">Aciertos</span>
                        </div>
                    </div>
                </section>

                {/* STATS GRID */}
                <section className="stats-grid">

                    {/* DESEMPEÑO: DONUT DINÁMICO */}
                    <div className="card">
                        <h3>
                            <i className="fas fa-chart-column"></i>
                            Desempeño General
                        </h3>

                        <div className="donut-chart">
                            <svg viewBox="0 0 42 42">
                                {/* Fondo */}
                                <circle
                                    cx="21" cy="21" r="15.915"
                                    fill="transparent"
                                    stroke="#1E293B"
                                    strokeWidth="3"
                                />

                                {/* Correctas */}
                                <circle
                                    cx="21" cy="21" r="15.915"
                                    fill="transparent"
                                    stroke="#22C55E"
                                    strokeWidth="3"
                                    strokeDasharray={correctDash}
                                    strokeLinecap="round"
                                    transform="rotate(-90 21 21)"
                                />

                                {/* Incorrectas */}
                                <circle
                                    cx="21" cy="21" r="15.915"
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
                                    cx="21" cy="21" r="15.915"
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

                    {/* RENDIMIENTO POR MATERIA */}
                    <div className="card">
                        <h3>
                            <i className="fas fa-book-open"></i>
                            Resultados por Área
                        </h3>

                        {subjects.length > 0 ? (
                            subjects.map((subj, idx) => {
                                const label = subj.total > 0
                                    ? `${subj.score}/${subj.total}`
                                    : `${subj.percent}%`;

                                const widthPct = Math.min(100, Math.max(0, subj.percent));

                                return (
                                    <div className="subject" key={idx}>
                                        <div className="subject__top">
                                            <span>{subj.name}</span>
                                            <span>{label}</span>
                                        </div>
                                        <div className="subject__bar">
                                            <div
                                                className={`subject__fill ${subj.color}`}
                                                style={{ width: `${widthPct}%` }}
                                            ></div>
                                        </div>
                                    </div>
                                );
                            })
                        ) : (
                            <p style={{ color: "var(--text-secondary)", fontSize: "14px" }}>
                                No se recibió desglose por materia.
                            </p>
                        )}
                    </div>

                </section>

                {/* BOTONES DE ACCIÓN */}
                <section className="actions">
                    <Link to={`/mock/review?id=${examId}`}>
                        <button className="btn btn--secondary">
                            <i className="fas fa-list-check"></i>
                            Revisar Respuestas
                        </button>
                    </Link>

                    <Link to="/ai-tutor">
                        <button className="btn btn--primary">
                            <i className="fas fa-robot"></i>
                            Explicación con Tutor IA
                        </button>
                    </Link>

                    <Link to="/dashboard">
                        <button className="btn btn--ghost">
                            <i className="fas fa-house"></i>
                            Volver al Dashboard
                        </button>
                    </Link>
                </section>

            </div>
        </>
    );
};

export default MockResults;
