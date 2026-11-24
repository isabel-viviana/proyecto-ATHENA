import { Outlet, Link, useLocation } from "react-router-dom";

const MockLayout = () => {
    const location = useLocation();
    const isTakingExam = location.pathname.startsWith("/mock/exam");

    const navItemStyle = (path) => {
        const active =
            location.pathname === path ||
            (path === "/mock/config" && location.pathname === "/mock");
        return {
            padding: "8px 14px",
            borderRadius: "8px",
            textDecoration: "none",
            fontSize: "13px",
            fontWeight: 600,
            background: active ? "rgba(2, 132, 199, 0.25)" : "transparent",
            border: active
                ? "1px solid rgba(56, 189, 248, 0.45)"
                : "1px solid transparent",
            color: active ? "#38bdf8" : "#94a3b8",
            display: "inline-flex",
            alignItems: "center",
            gap: "6px",
            transition: "all 0.15s ease",
        };
    };

    return (
        <div className="mock-layout">
            {!isTakingExam && (
                <header
                    className="mock-header"
                    style={{
                        display: "flex",
                        gap: 12,
                        alignItems: "center",
                        padding: "14px 24px 0",
                        maxWidth: "1000px",
                        margin: "0 auto",
                    }}
                >
                    <nav style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
                        <Link to="/mock/config" style={navItemStyle("/mock/config")}>
                            <i className="fas fa-sliders"></i> Configurar Simulacro
                        </Link>
                        <Link to="/mock/quick" style={navItemStyle("/mock/quick")}>
                            <i className="fas fa-bolt"></i> Mini Simulacro Rápido
                        </Link>
                        <Link to="/mock/history" style={navItemStyle("/mock/history")}>
                            <i className="fas fa-clock-rotate-left"></i> Historial
                        </Link>
                    </nav>
                </header>
            )}

            <Outlet />
        </div>
    );
};

export default MockLayout;
