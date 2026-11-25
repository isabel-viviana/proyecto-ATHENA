import "./mock_config.css";
import { useState, useEffect } from "react";
import { useNavigate, Link } from "react-router-dom";
import Zorrito from "../../assets/images/Zorrito2D.png";
import { startExam, getActiveExam, getExamConfigOptions } from "../../api/exams";

const ICFES_SESSIONS_STRUCTURE = [
    {
        sessionId: "s1",
        title: "Primera sesión (mañana — 143 preguntas)",
        icon: "fa-sun",
        items: [
            {
                key: "s1-matematicas",
                order: 1,
                name: "Matemáticas",
                subtitle: "25 preguntas",
                matchKey: "matematicas",
                defaultSubjectId: 2,
                sessionLabel: "Primera sesión (Mañana)",
            },
            {
                key: "s1-lectura-critica",
                order: 2,
                name: "Lectura Crítica",
                subtitle: "41 preguntas — se evalúa completa aquí",
                matchKey: "lectura",
                defaultSubjectId: 1,
                sessionLabel: "Primera sesión (Mañana)",
            },
            {
                key: "s1-sociales",
                order: 3,
                name: "Sociales y Ciudadanas",
                subtitle: "25 preguntas",
                matchKey: "sociales",
                defaultSubjectId: 3,
                sessionLabel: "Primera sesión (Mañana)",
            },
            {
                key: "s1-ciencias",
                order: 4,
                name: "Ciencias Naturales",
                subtitle: "29 preguntas",
                matchKey: "ciencias",
                defaultSubjectId: 4,
                sessionLabel: "Primera sesión (Mañana)",
            },
            {
                key: "s1-auxiliares",
                order: 5,
                name: "Cuestionarios auxiliares",
                subtitle: "11 socioeconómico + 12 clima escolar",
                matchKey: null,
                defaultSubjectId: null,
                isAuxiliary: true,
                auxiliaryBadge: "No calificable",
                sessionLabel: "Primera sesión (Mañana)",
            },
        ],
    },
    {
        sessionId: "s2",
        title: "Segunda sesión (tarde — 159 preguntas)",
        icon: "fa-clock",
        items: [
            {
                key: "s2-sociales",
                order: 1,
                name: "Sociales y Ciudadanas",
                subtitle: "25 preguntas",
                matchKey: "sociales",
                defaultSubjectId: 3,
                sessionLabel: "Segunda sesión (Tarde)",
            },
            {
                key: "s2-matematicas",
                order: 2,
                name: "Matemáticas",
                subtitle: "25 preguntas",
                matchKey: "matematicas",
                defaultSubjectId: 2,
                sessionLabel: "Segunda sesión (Tarde)",
            },
            {
                key: "s2-ciencias",
                order: 3,
                name: "Ciencias Naturales",
                subtitle: "29 preguntas",
                matchKey: "ciencias",
                defaultSubjectId: 4,
                sessionLabel: "Segunda sesión (Tarde)",
            },
            {
                key: "s2-ingles",
                order: 4,
                name: "Inglés",
                subtitle: "55 preguntas — se evalúa completa aquí",
                matchKey: "ingles",
                defaultSubjectId: 5,
                sessionLabel: "Segunda sesión (Tarde)",
            },
        ],
    },
];

const normalizeText = (text = "") =>
    String(text)
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase();

const MockConfig = () => {
    const navigate = useNavigate();

    // Modalidad: 'area' (por área) o 'custom' (personalizado)
    const [mode, setMode] = useState("area");
    const [selectedItemKey, setSelectedItemKey] = useState("s1-lectura-critica");
    const [selectedSubjectId, setSelectedSubjectId] = useState(1); // 1 = Lectura Crítica
    const [questionsCount, setQuestionsCount] = useState(49);
    const [durationMinutes, setDurationMinutes] = useState(98);

    // Opciones del backend y estado activo
    const [activeAttempt, setActiveAttempt] = useState(null);
    const [areas, setAreas] = useState([]);
    const [loading, setLoading] = useState(false);
    const [initialLoading, setInitialLoading] = useState(true);
    const [error, setError] = useState("");

    // Tiempos recomendados por número de reactivos (~2 minutos por pregunta)
    const RECOMMENDED_TIMES = {
        10: 20,
        20: 40,
        25: 50,
        49: 98,
    };

    const resolveBackendArea = (item, areasList = areas) => {
        if (!item || item.isAuxiliary || !item.matchKey) return null;
        return (
            areasList.find((a) => {
                const normName = normalizeText(a.name);
                const normCode = normalizeText(a.code);
                return normName.includes(item.matchKey) || normCode.includes(item.matchKey);
            }) || null
        );
    };

    useEffect(() => {
        const loadInitialData = async () => {
            try {
                setInitialLoading(true);
                // 1. Verificar si hay un intento activo
                const activeRes = await getActiveExam();
                if (activeRes && activeRes.success && activeRes.data && activeRes.data.attempt) {
                    setActiveAttempt(activeRes.data.attempt);
                }

                // 2. Cargar opciones del catálogo
                const configRes = await getExamConfigOptions();
                if (configRes && configRes.success && configRes.data) {
                    const loadedAreas = configRes.data.areas || [];
                    setAreas(loadedAreas);

                    // Sincronizar la selección por defecto con la primera área disponible (Lectura Crítica)
                    const lecturaBackend = loadedAreas.find((a) =>
                        normalizeText(a.name).includes("lectura")
                    );
                    if (lecturaBackend && lecturaBackend.available) {
                        setSelectedItemKey("s1-lectura-critica");
                        setSelectedSubjectId(lecturaBackend.id);
                    } else {
                        for (const session of ICFES_SESSIONS_STRUCTURE) {
                            let found = false;
                            for (const item of session.items) {
                                const matched = resolveBackendArea(item, loadedAreas);
                                if (matched && matched.available) {
                                    setSelectedItemKey(item.key);
                                    setSelectedSubjectId(matched.id);
                                    found = true;
                                    break;
                                }
                            }
                            if (found) break;
                        }
                    }
                }
            } catch (err) {
                console.error("Error al cargar configuración:", err);
            } finally {
                setInitialLoading(false);
            }
        };

        loadInitialData();
    }, []);

    const handleQuestionsCountChange = (count) => {
        setQuestionsCount(count);
        setDurationMinutes(RECOMMENDED_TIMES[count] || count * 2);
    };

    const handleStartExam = async () => {
        setError("");
        setLoading(true);

        try {
            const res = await startExam({
                subject_id: selectedSubjectId,
                mode: mode,
                questions_count: questionsCount,
                duration_minutes: durationMinutes,
            });

            if (res && res.success && res.data && res.data.attempt) {
                const examId = res.data.attempt.id;
                navigate(`/mock/exam?id=${examId}`);
            } else {
                throw new Error(res?.message || "No se pudo iniciar el simulacro.");
            }
        } catch (err) {
            console.error("Error al iniciar simulacro:", err);
            setError(
                err?.response?.data?.message ||
                err?.message ||
                "Ocurrió un error al generar la evaluación."
            );
        } finally {
            setLoading(false);
        }
    };

    if (initialLoading) {
        return (
            <div className="main__content" style={{ textAlign: "center", padding: "80px 20px" }}>
                <div style={{ fontSize: "36px", color: "#38bdf8", marginBottom: "16px" }}>
                    <i className="fas fa-spinner fa-spin"></i>
                </div>
                <h2 style={{ color: "#f8fafc", fontSize: "20px" }}>Cargando configuración de simulacros...</h2>
            </div>
        );
    }

    return (
        <section className="mock-config">
            <div className="main__content">
                {/* HEADER */}
                <section className="page-header" style={{ marginBottom: "20px" }}>
                    <div className="page-header__info">
                        <h1>Configuración de Simulacro Oficial ICFES</h1>
                        <p>
                            Presenta una prueba estructurada bajo condiciones reglamentarias: tiempo límite, navegación entre preguntas, control de marcas y calificación al entregar.
                        </p>
                    </div>
                </section>

                {/* ALERTA DE INTENTO EN PROGRESO (RECUPERACIÓN DE SESIÓN) */}
                {activeAttempt && (
                    <div style={{
                        background: "linear-gradient(135deg, rgba(2, 132, 199, 0.25) 0%, rgba(15, 23, 42, 0.9) 100%)",
                        border: "1px solid #38bdf8",
                        borderRadius: "14px",
                        padding: "18px 22px",
                        marginBottom: "24px",
                        display: "flex",
                        justifyContent: "space-between",
                        alignItems: "center",
                        flexWrap: "wrap",
                        gap: "14px",
                    }}>
                        <div>
                            <div style={{ display: "flex", alignItems: "center", gap: "8px", color: "#38bdf8", fontWeight: 700, fontSize: "14px" }}>
                                <i className="fas fa-clock-rotate-left"></i>
                                <span>TIENES UN SIMULACRO ACTIVO EN CURSO</span>
                            </div>
                            <h3 style={{ color: "#f8fafc", margin: "4px 0 2px 0", fontSize: "16px" }}>
                                {activeAttempt.title}
                            </h3>
                            <p style={{ color: "#94a3b8", fontSize: "13px", margin: 0 }}>
                                {activeAttempt.questions_count} reactivos · {Math.round(activeAttempt.remaining_seconds / 60)} min restantes
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={() => navigate(`/mock/exam?id=${activeAttempt.id}`)}
                            style={{
                                background: "#0284c7",
                                color: "#ffffff",
                                padding: "10px 20px",
                                borderRadius: "8px",
                                border: "none",
                                fontWeight: 700,
                                cursor: "pointer",
                                display: "inline-flex",
                                alignItems: "center",
                                gap: "8px",
                            }}
                        >
                            <i className="fas fa-play"></i> Continuar Simulacro
                        </button>
                    </div>
                )}

                {/* BANNER INFORMATIVO ICFES */}
                <div style={{
                    background: "rgba(56, 189, 248, 0.08)",
                    border: "1px solid rgba(56, 189, 248, 0.3)",
                    borderRadius: "12px",
                    padding: "14px 18px",
                    marginBottom: "24px",
                    display: "flex",
                    alignItems: "center",
                    gap: "12px",
                    color: "#cbd5e1",
                    fontSize: "13.5px",
                    lineHeight: "1.5",
                }}>
                    <i className="fas fa-circle-info" style={{ color: "#38bdf8", fontSize: "20px" }}></i>
                    <div>
                        <strong style={{ color: "#38bdf8" }}>Simulacro Parcial Oficial: </strong>
                        Actualmente está disponible el cuadernillo completo de <strong>Lectura Crítica</strong> (49 preguntas oficiales con textos continuos, caricaturas de Quino, viñetas de Calvin & Hobbes e infografías del tránsito). Las otras 4 áreas ICFES se habilitarán en próximas actualizaciones.
                    </div>
                </div>

                {error && (
                    <div style={{
                        padding: "12px 16px",
                        borderRadius: "8px",
                        background: "rgba(239, 68, 68, 0.15)",
                        border: "1px solid rgba(239, 68, 68, 0.35)",
                        color: "#f87171",
                        marginBottom: "20px",
                        fontSize: "14px",
                    }}>
                        <i className="fas fa-triangle-exclamation" style={{ marginRight: "8px" }}></i>
                        {error}
                    </div>
                )}

                {/* CONFIG GRID */}
                <section className="config-grid">
                    {/* PANEL IZQUIERDO: MODALIDAD Y ÁREAS */}
                    <div className="config-card">
                        <div className="card-title">
                            <i className="fas fa-gear"></i>
                            <h3>1. Modalidad de Evaluación</h3>
                        </div>

                        {/* MODALIDAD 1: POR ÁREA */}
                        <label
                            className={`option ${mode === "area" ? "option--active" : ""}`}
                            onClick={() => setMode("area")}
                        >
                            <div className="option__top">
                                <div className="option__left">
                                    <input
                                        type="radio"
                                        name="examMode"
                                        checked={mode === "area"}
                                        onChange={() => setMode("area")}
                                    />
                                    <div>
                                        <h4>Simulacro Oficial por Área</h4>
                                        <p>Evaluación reglamentaria sobre un cuadernillo oficial de Saber 11.</p>
                                    </div>
                                </div>
                                <span className="recommended">Recomendado</span>
                            </div>
                        </label>

                        {/* MODALIDAD 2: PERSONALIZADO */}
                        <label
                            className={`option ${mode === "custom" ? "option--active" : ""}`}
                            onClick={() => setMode("custom")}
                        >
                            <div className="option__top">
                                <div className="option__left">
                                    <input
                                        type="radio"
                                        name="examMode"
                                        checked={mode === "custom"}
                                        onChange={() => setMode("custom")}
                                    />
                                    <div>
                                        <h4>Simulacro Personalizado</h4>
                                        <p>Ajusta el número de preguntas y el tiempo disponible a tu medida.</p>
                                    </div>
                                </div>
                                <span style={{ fontSize: "11px", color: "#38bdf8", fontWeight: 600 }}>Flexible</span>
                            </div>
                        </label>

                        {/* SELECCIÓN DE ÁREA OFICIAL POR SESIONES SABER 11 */}
                        <div className="icfes-area-section">
                            <h4 className="icfes-area-section__title">
                                2. Área de Evaluación
                            </h4>

                            <div className="icfes-sessions-container">
                                {ICFES_SESSIONS_STRUCTURE.map((session) => (
                                    <div key={session.sessionId} className="icfes-session-block">
                                        <div className="icfes-session-block__header">
                                            <i className={`fas ${session.icon}`}></i>
                                            <span>{session.title}</span>
                                        </div>

                                        <div className="icfes-session-block__list">
                                            {session.items.map((item) => {
                                                const backendArea = resolveBackendArea(item);
                                                const isAvailable =
                                                    !item.isAuxiliary &&
                                                    (backendArea
                                                        ? Boolean(backendArea.available)
                                                        : item.matchKey === "lectura");
                                                const publishedCount = backendArea
                                                    ? backendArea.published_count
                                                    : item.matchKey === "lectura"
                                                    ? 49
                                                    : 0;
                                                const targetSubjectId = backendArea
                                                    ? backendArea.id
                                                    : item.defaultSubjectId;
                                                const isSelected =
                                                    isAvailable && selectedItemKey === item.key;

                                                return (
                                                    <div
                                                        key={item.key}
                                                        onClick={() => {
                                                            if (isAvailable && targetSubjectId) {
                                                                setSelectedItemKey(item.key);
                                                                setSelectedSubjectId(targetSubjectId);
                                                            }
                                                        }}
                                                        className={`icfes-area-item ${
                                                            isSelected ? "icfes-area-item--selected" : ""
                                                        } ${
                                                            !isAvailable ? "icfes-area-item--disabled" : ""
                                                        }`}
                                                    >
                                                        <div className="icfes-area-item__left">
                                                            <i
                                                                className={`fas ${
                                                                    isSelected
                                                                        ? "fa-circle-dot"
                                                                        : "fa-circle"
                                                                } icfes-area-item__radio`}
                                                            ></i>
                                                            <span className="icfes-area-item__order">
                                                                {item.order}.
                                                            </span>
                                                            <div className="icfes-area-item__texts">
                                                                <span className="icfes-area-item__name">
                                                                    {item.name}
                                                                </span>
                                                                <span className="icfes-area-item__subtitle">
                                                                    {item.subtitle}
                                                                </span>
                                                            </div>
                                                        </div>

                                                        <span
                                                            className={`icfes-area-item__badge ${
                                                                isAvailable
                                                                    ? "icfes-area-item__badge--available"
                                                                    : item.isAuxiliary
                                                                    ? "icfes-area-item__badge--auxiliary"
                                                                    : "icfes-area-item__badge--upcoming"
                                                            }`}
                                                        >
                                                            {isAvailable
                                                                ? `${publishedCount} Reactivos`
                                                                : item.isAuxiliary
                                                                ? item.auxiliaryBadge
                                                                : "Próximamente"}
                                                        </span>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* PANEL DERECHO: CANTIDAD, DURACIÓN Y RESUMEN */}
                    <div className="config-card">
                        <div className="card-title">
                            <i className="fas fa-sliders"></i>
                            <h3>3. Cantidad de Preguntas y Tiempo</h3>
                        </div>

                        {/* SELECTOR DE NÚMERO DE PREGUNTAS */}
                        <div style={{ marginBottom: "20px" }}>
                            <label style={{ display: "block", color: "#94a3b8", fontSize: "13px", marginBottom: "8px" }}>
                                Cantidad de Reactivos a Resolver:
                            </label>
                            <div style={{ display: "grid", gridTemplateColumns: "repeat(2, 1fr)", gap: "10px" }}>
                                {[10, 20, 25, 49].map((cnt) => (
                                    <button
                                        key={cnt}
                                        type="button"
                                        onClick={() => handleQuestionsCountChange(cnt)}
                                        style={{
                                            padding: "12px",
                                            borderRadius: "10px",
                                            border: questionsCount === cnt ? "2px solid #38bdf8" : "1px solid rgba(148, 163, 184, 0.2)",
                                            background: questionsCount === cnt ? "rgba(2, 132, 199, 0.25)" : "rgba(15, 23, 42, 0.6)",
                                            color: questionsCount === cnt ? "#38bdf8" : "#cbd5e1",
                                            fontWeight: 700,
                                            fontSize: "14px",
                                            cursor: "pointer",
                                            textAlign: "center",
                                        }}
                                    >
                                        {cnt === 49 ? "49 (Completo)" : `${cnt} preguntas`}
                                        <span style={{ display: "block", fontSize: "11px", fontWeight: 400, color: "#94a3b8", marginTop: "2px" }}>
                                            ~{RECOMMENDED_TIMES[cnt]} min sugeridos
                                        </span>
                                    </button>
                                ))}
                            </div>
                        </div>

                        {/* DURACIÓN */}
                        <div style={{ marginBottom: "24px" }}>
                            <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "6px" }}>
                                <label style={{ color: "#94a3b8", fontSize: "13px" }}>
                                    Límite de Tiempo:
                                </label>
                                <span style={{ color: "#38bdf8", fontWeight: 700, fontSize: "14px" }}>
                                    {durationMinutes} minutos ({Math.floor(durationMinutes / 60)}h {durationMinutes % 60}m)
                                </span>
                            </div>
                            <input
                                type="range"
                                min={Math.max(5, Math.round(questionsCount * 1))}
                                max={Math.max(60, Math.round(questionsCount * 3))}
                                value={durationMinutes}
                                onChange={(e) => setDurationMinutes(parseInt(e.target.value, 10))}
                                style={{ width: "100%", accentColor: "#0284c7", cursor: "pointer" }}
                            />
                            <div style={{ display: "flex", justifyContent: "space-between", fontSize: "11px", color: "#64748b", marginTop: "4px" }}>
                                <span>Rápido ({Math.max(5, Math.round(questionsCount * 1))}m)</span>
                                <span>Recomendado: {RECOMMENDED_TIMES[questionsCount]}m</span>
                                <span>Holgado ({Math.round(questionsCount * 3)}m)</span>
                            </div>
                        </div>

                        {/* RESUMEN DE LA CONFIGURACIÓN */}
                        <div style={{
                            background: "rgba(15, 23, 42, 0.8)",
                            borderRadius: "12px",
                            border: "1px solid rgba(148, 163, 184, 0.15)",
                            padding: "16px",
                            marginBottom: "24px",
                        }}>
                            <h4 style={{ color: "#f8fafc", fontSize: "13px", textTransform: "uppercase", letterSpacing: "0.5px", margin: "0 0 10px 0" }}>
                                Resumen del Simulacro
                            </h4>
                            <div style={{ display: "flex", flexDirection: "column", gap: "8px", fontSize: "13px" }}>
                                {(() => {
                                    const activeItem =
                                        ICFES_SESSIONS_STRUCTURE.flatMap((s) => s.items).find(
                                            (i) => i.key === selectedItemKey
                                        ) || ICFES_SESSIONS_STRUCTURE[0].items[1];
                                    return (
                                        <>
                                            <div style={{ display: "flex", justifyContent: "space-between" }}>
                                                <span style={{ color: "#94a3b8" }}>Materia:</span>
                                                <strong style={{ color: "#f8fafc" }}>
                                                    {activeItem.name} Saber 11
                                                </strong>
                                            </div>
                                            <div style={{ display: "flex", justifyContent: "space-between" }}>
                                                <span style={{ color: "#94a3b8" }}>Sesión Oficial:</span>
                                                <span style={{ color: "#cbd5e1", fontWeight: 600 }}>
                                                    {activeItem.sessionLabel}
                                                </span>
                                            </div>
                                        </>
                                    );
                                })()}
                                <div style={{ display: "flex", justifyContent: "space-between" }}>
                                    <span style={{ color: "#94a3b8" }}>Total de Reactivos:</span>
                                    <strong style={{ color: "#38bdf8" }}>{questionsCount} preguntas</strong>
                                </div>
                                <div style={{ display: "flex", justifyContent: "space-between" }}>
                                    <span style={{ color: "#94a3b8" }}>Tiempo Límite:</span>
                                    <strong style={{ color: "#4ade80" }}>{durationMinutes} minutos</strong>
                                </div>
                                <div style={{ display: "flex", justifyContent: "space-between" }}>
                                    <span style={{ color: "#94a3b8" }}>Calificación:</span>
                                    <span style={{ color: "#cbd5e1" }}>Al finalizar y entregar examen</span>
                                </div>
                            </div>
                        </div>

                        {/* BOTÓN INICIAR */}
                        <button
                            type="button"
                            onClick={handleStartExam}
                            disabled={loading}
                            style={{
                                width: "100%",
                                padding: "14px",
                                background: "linear-gradient(135deg, #0284c7 0%, #38bdf8 100%)",
                                border: "none",
                                borderRadius: "10px",
                                color: "#ffffff",
                                fontSize: "15px",
                                fontWeight: 700,
                                cursor: loading ? "wait" : "pointer",
                                display: "flex",
                                alignItems: "center",
                                justifyContent: "center",
                                gap: "10px",
                                boxShadow: "0 10px 25px rgba(2, 132, 199, 0.3)",
                            }}
                        >
                            <i className="fas fa-play"></i>
                            {loading ? "Generando simulacro..." : "Comenzar Evaluación"}
                        </button>
                    </div>
                </section>

                {/* ASISTENTE ZORRITO ATHENA */}
                <div className="assistant">
                    <div className="assistant__message">
                        ¡Concéntrate! Administra bien tu tiempo y recuerda que puedes marcar preguntas para revisarlas antes de finalizar.
                    </div>
                    <div className="assistant__bot">
                        <img src={Zorrito} alt="Zorrito" />
                    </div>
                </div>
            </div>
        </section>
    );
};

export default MockConfig;
