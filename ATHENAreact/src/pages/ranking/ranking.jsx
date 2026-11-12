import "./ranking.css";
import { useState, useEffect } from "react";
import { Link } from "react-router-dom";
import { getStudentRanking } from "../../api/studentQuestions";

const Ranking = () => {
    const [rankingData, setRankingData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");

    const fetchRanking = async () => {
        try {
            setLoading(true);
            setError("");
            const res = await getStudentRanking();
            if (res && res.success && res.data) {
                setRankingData(res.data);
            } else {
                setError(res?.message || "No se pudo obtener el ranking institucional.");
            }
        } catch (err) {
            console.error("Error al cargar ranking:", err);
            setError(err?.response?.data?.message || "Error al conectar con el servidor de ranking.");
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchRanking();
    }, []);

    const top3 = rankingData?.top_3 || [];
    const fullList = rankingData?.ranking || [];
    const myRank = rankingData?.my_rank || null;
    const totalParticipants = rankingData?.total_participants || 0;

    const getMedalColor = (rank) => {
        if (rank === 1) return { color: "#eab308", label: "Oro", icon: "fa-crown", bg: "rgba(234, 179, 8, 0.12)", border: "rgba(234, 179, 8, 0.35)" };
        if (rank === 2) return { color: "#94a3b8", label: "Plata", icon: "fa-medal", bg: "rgba(148, 163, 184, 0.12)", border: "rgba(148, 163, 184, 0.35)" };
        if (rank === 3) return { color: "#d97706", label: "Bronce", icon: "fa-award", bg: "rgba(217, 119, 6, 0.12)", border: "rgba(217, 119, 6, 0.35)" };
        return { color: "#38bdf8", label: "", icon: "fa-trophy", bg: "rgba(56, 189, 248, 0.1)", border: "rgba(56, 189, 248, 0.25)" };
    };

    return (
        <div className="ranking-page">
            {/* CABECERA */}
            <section className="page-header">
                <div className="page-header__info">
                    <h1>Ranking Oficial de Estudiantes ATHENA</h1>
                    <p>
                        Clasificación académica calculada en tiempo real a partir del desempeño en reactivos reales del ICFES. La puntuación premia los reactivos únicos acertados y la efectividad porcentual.
                    </p>
                </div>
                <button
                    onClick={fetchRanking}
                    style={{
                        padding: "8px 16px",
                        background: "rgba(56, 189, 248, 0.12)",
                        border: "1px solid rgba(56, 189, 248, 0.3)",
                        borderRadius: "8px",
                        color: "#38bdf8",
                        cursor: "pointer",
                        fontSize: "13px",
                        fontWeight: 600,
                        display: "inline-flex",
                        alignItems: "center",
                        gap: "6px",
                    }}
                >
                    <i className="fas fa-arrows-rotate"></i>
                    Actualizar Ranking
                </button>
            </section>

            {/* ESTADO DE CARGA */}
            {loading && (
                <div style={{ textAlign: "center", padding: "60px 20px" }}>
                    <div style={{ fontSize: "36px", color: "#38bdf8", marginBottom: "16px" }}>
                        <i className="fas fa-spinner fa-spin"></i>
                    </div>
                    <p style={{ color: "#94a3b8" }}>Calculando clasificación oficial...</p>
                </div>
            )}

            {/* ESTADO DE ERROR */}
            {error && (
                <div style={{ padding: "16px", borderRadius: "12px", background: "rgba(239, 68, 68, 0.12)", border: "1px solid rgba(239, 68, 68, 0.3)", marginBottom: "24px", color: "#f87171" }}>
                    <i className="fas fa-triangle-exclamation" style={{ marginRight: "8px" }}></i>
                    {error}
                </div>
            )}

            {!loading && !error && (
                <>
                    {/* TU POSICIÓN ACTUAL (DESTACADO PARA EL ESTUDIANTE AUTENTICADO) */}
                    {myRank && (
                        <div style={{
                            background: "linear-gradient(135deg, rgba(2, 132, 199, 0.25) 0%, rgba(15, 23, 42, 0.9) 100%)",
                            border: "1px solid #38bdf8",
                            borderRadius: "16px",
                            padding: "20px 24px",
                            marginBottom: "28px",
                            display: "flex",
                            justifyContent: "space-between",
                            alignItems: "center",
                            flexWrap: "wrap",
                            gap: "16px",
                            boxShadow: "0 10px 25px rgba(2, 132, 199, 0.15)",
                        }}>
                            <div style={{ display: "flex", alignItems: "center", gap: "16px" }}>
                                <div style={{
                                    width: "56px",
                                    height: "56px",
                                    borderRadius: "50%",
                                    background: "#0284c7",
                                    color: "#ffffff",
                                    fontSize: "22px",
                                    fontWeight: 800,
                                    display: "flex",
                                    alignItems: "center",
                                    justifyContent: "center",
                                    border: "2px solid #38bdf8",
                                }}>
                                    #{myRank.rank}
                                </div>
                                <div>
                                    <span style={{ color: "#38bdf8", fontSize: "12px", fontWeight: 700, textTransform: "uppercase", letterSpacing: "0.5px" }}>
                                        Tu Posición Actual
                                    </span>
                                    <h2 style={{ color: "#f8fafc", fontSize: "18px", margin: "2px 0 0 0" }}>
                                        {myRank.full_name} <span style={{ color: "#94a3b8", fontSize: "14px", fontWeight: 400 }}>({myRank.grade})</span>
                                    </h2>
                                </div>
                            </div>

                            <div style={{ display: "flex", gap: "24px", alignItems: "center" }}>
                                <div style={{ textAlign: "center" }}>
                                    <span style={{ color: "#94a3b8", fontSize: "11px", display: "block" }}>Puntuación</span>
                                    <strong style={{ color: "#f8fafc", fontSize: "20px" }}>{myRank.score} pts</strong>
                                </div>
                                <div style={{ textAlign: "center" }}>
                                    <span style={{ color: "#94a3b8", fontSize: "11px", display: "block" }}>Aciertos Únicos</span>
                                    <strong style={{ color: "#4ade80", fontSize: "20px" }}>{myRank.distinct_correct}</strong>
                                </div>
                                <div style={{ textAlign: "center" }}>
                                    <span style={{ color: "#94a3b8", fontSize: "11px", display: "block" }}>Efectividad</span>
                                    <strong style={{ color: "#38bdf8", fontSize: "20px" }}>{myRank.accuracy}%</strong>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* TOP 3 PODIO DESTACADO */}
                    <div style={{ marginBottom: "16px" }}>
                        <h3 style={{ color: "#f8fafc", fontSize: "18px", margin: "0 0 14px 0", display: "flex", alignItems: "center", gap: "8px" }}>
                            <i className="fas fa-trophy" style={{ color: "#eab308" }}></i>
                            Podio de Excelencia Académica (Top 3)
                        </h3>
                    </div>

                    <div className="ranking-grid" style={{ marginBottom: "32px" }}>
                        {top3.map((student) => {
                            const badge = getMedalColor(student.rank);
                            return (
                                <article
                                    key={student.student_id}
                                    className="ranking-card"
                                    style={{
                                        background: badge.bg,
                                        border: `1px solid ${badge.border}`,
                                        position: "relative",
                                        overflow: "hidden",
                                    }}
                                >
                                    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                                        <span style={{ color: badge.color, fontSize: "12px", fontWeight: 700, textTransform: "uppercase", display: "flex", alignItems: "center", gap: "6px" }}>
                                            <i className={`fas ${badge.icon}`}></i> Posición #{student.rank} • {badge.label}
                                        </span>
                                        {student.is_current_student && (
                                            <span style={{ background: "#0284c7", color: "#fff", padding: "2px 8px", borderRadius: "10px", fontSize: "11px", fontWeight: 700 }}>
                                                TÚ
                                            </span>
                                        )}
                                    </div>

                                    <div style={{ display: "flex", alignItems: "center", gap: "12px", margin: "6px 0" }}>
                                        <div style={{
                                            width: "44px",
                                            height: "44px",
                                            borderRadius: "50%",
                                            background: "rgba(15, 23, 42, 0.8)",
                                            color: badge.color,
                                            fontWeight: 800,
                                            fontSize: "18px",
                                            display: "flex",
                                            alignItems: "center",
                                            justifyContent: "center",
                                            border: `1px solid ${badge.color}`,
                                        }}>
                                            {student.public_name.charAt(0)}
                                        </div>
                                        <div>
                                            <h4 style={{ color: "#f8fafc", fontSize: "16px", margin: 0, fontWeight: 700 }}>
                                                {student.public_name}
                                            </h4>
                                            <span style={{ color: "#94a3b8", fontSize: "12px" }}>
                                                {student.grade} • Nivel {student.level}
                                            </span>
                                        </div>
                                    </div>

                                    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "baseline", borderTop: "1px dashed rgba(148, 163, 184, 0.2)", paddingTop: "10px", marginTop: "4px" }}>
                                        <div>
                                            <span style={{ color: "#94a3b8", fontSize: "11px", display: "block" }}>PUNTUACIÓN</span>
                                            <strong style={{ color: "#f8fafc", fontSize: "22px" }}>{student.score} pts</strong>
                                        </div>
                                        <div style={{ textAlign: "right" }}>
                                            <span style={{ color: "#94a3b8", fontSize: "11px", display: "block" }}>EFECTIVIDAD</span>
                                            <strong style={{ color: "#4ade80", fontSize: "16px" }}>{student.accuracy}%</strong>
                                        </div>
                                    </div>
                                </article>
                            );
                        })}
                    </div>

                    {/* TABLA COMPLETA DE PARTICIPANTES */}
                    <div className="ranking-table-wrapper">
                        <section className="table-card">
                            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "16px", flexWrap: "wrap", gap: "8px" }}>
                                <div>
                                    <h3 style={{ margin: 0 }}>Tabla General de Participantes</h3>
                                    <p style={{ margin: "4px 0 0 0", color: "#94a3b8", fontSize: "13px" }}>
                                        Total de {totalParticipants} estudiantes activos en la plataforma.
                                    </p>
                                </div>
                            </div>

                            <table>
                                <thead>
                                    <tr>
                                        <th style={{ width: "60px", textAlign: "center" }}>#</th>
                                        <th>Estudiante</th>
                                        <th>Grado</th>
                                        <th style={{ textAlign: "center" }}>Reactivos Acertados</th>
                                        <th style={{ textAlign: "center" }}>Efectividad</th>
                                        <th style={{ textAlign: "right" }}>Puntuación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {fullList.map((item) => {
                                        const isCurrent = item.is_current_student;
                                        return (
                                            <tr
                                                key={item.student_id}
                                                style={{
                                                    background: isCurrent ? "rgba(2, 132, 199, 0.12)" : undefined,
                                                    fontWeight: isCurrent ? 600 : 400,
                                                }}
                                            >
                                                <td className="rank-number" style={{ textAlign: "center", color: item.rank <= 3 ? "#facc15" : undefined }}>
                                                    {item.rank <= 3 ? (
                                                        <i className="fas fa-crown" style={{ marginRight: "4px", fontSize: "12px" }}></i>
                                                    ) : null}
                                                    {item.rank}
                                                </td>
                                                <td>
                                                    <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
                                                        <div style={{
                                                            width: "32px",
                                                            height: "32px",
                                                            borderRadius: "50%",
                                                            background: isCurrent ? "#0284c7" : "rgba(148, 163, 184, 0.2)",
                                                            color: "#f8fafc",
                                                            fontSize: "13px",
                                                            fontWeight: 700,
                                                            display: "flex",
                                                            alignItems: "center",
                                                            justifyContent: "center",
                                                        }}>
                                                            {item.public_name.charAt(0)}
                                                        </div>
                                                        <div>
                                                            <span style={{ color: isCurrent ? "#38bdf8" : "#f8fafc" }}>
                                                                {item.public_name}
                                                            </span>
                                                            {isCurrent && (
                                                                <span style={{ marginLeft: "8px", background: "#0284c7", color: "#fff", padding: "1px 6px", borderRadius: "8px", fontSize: "10px" }}>
                                                                    Tú
                                                                </span>
                                                            )}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td style={{ color: "#94a3b8" }}>{item.grade}</td>
                                                <td style={{ textAlign: "center", color: "#cbd5e1" }}>
                                                    {item.distinct_correct} reactivos
                                                </td>
                                                <td style={{ textAlign: "center" }}>
                                                    <span style={{ color: item.accuracy >= 75 ? "#4ade80" : item.accuracy >= 50 ? "#38bdf8" : "#94a3b8" }}>
                                                        {item.accuracy}%
                                                    </span>
                                                </td>
                                                <td style={{ textAlign: "right", fontWeight: 700, color: "#f8fafc" }}>
                                                    {item.score} pts
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </section>
                    </div>
                </>
            )}
        </div>
    );
};

export default Ranking;
