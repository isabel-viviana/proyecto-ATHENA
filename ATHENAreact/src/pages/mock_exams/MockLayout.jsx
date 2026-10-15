import { useEffect, useState } from "react";
import { Outlet, Link } from "react-router-dom";

const MockLayout = () => {
    const [timerSeconds, setTimerSeconds] = useState(0);
    const [timerRunning, setTimerRunning] = useState(false);

    useEffect(() => {
        /* importante: el intervalo se crea/destruye segun timerRunning
        asi evitamos acumulacion de timers y drift en UI */
        let interval;
        if (timerRunning) {
            interval = setInterval(() => {
                setTimerSeconds((s) => s + 1);
            }, 1000);
        }
        return () => clearInterval(interval);
    }, [timerRunning]);

    const startTimer = () => setTimerRunning(true);
    const stopTimer = () => setTimerRunning(false);
    const resetTimer = () => {
        setTimerSeconds(0);
        setTimerRunning(false);
    };

    return (
        <div className="mock-layout">
            <header
                className="mock-header"
                style={{
                    display: "flex",
                    gap: 12,
                    alignItems: "center",
                    marginBottom: 16,
                }}
            >
                <nav style={{ display: "flex", gap: 8 }}>
                    <Link to="/mock/config">Config</Link>
                    <Link to="/mock/quick">Rápido</Link>
                    <Link to="/mock/exam">Exam</Link>
                    <Link to="/mock/result">Resultados</Link>
                    <Link to="/mock/history">Historial</Link>
                </nav>

                <div
                    style={{
                        marginLeft: "auto",
                        display: "flex",
                        gap: 8,
                        alignItems: "center",
                    }}
                >
                    <small>
                        Timer: {Math.floor(timerSeconds / 60)}:
                        {String(timerSeconds % 60).padStart(2, "0")}
                    </small>
                    <button type="button" onClick={startTimer}>
                        Start
                    </button>
                    <button type="button" onClick={stopTimer}>
                        Stop
                    </button>
                    <button type="button" onClick={resetTimer}>
                        Reset
                    </button>
                </div>
            </header>

            <Outlet />
        </div>
    );
};

export default MockLayout;
