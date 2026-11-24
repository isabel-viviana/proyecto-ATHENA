import { Outlet, useLocation } from "react-router-dom";
import { AnimatePresence, motion } from "framer-motion";
import Sidebar from "./Sidebar";
import Footer from "./footer/Footer";

const MainLayout = () => {
    const location = useLocation();

    /* importante: key={location.pathname} fuerza el re-montaje de cada ruta
    asi la animacion de framer-motion no se “estanca” cuando cambias de pagina */


    return (
        <div
            className="layout"
            style={{
                display: "flex",
                minHeight: "100vh",
                backgroundColor: "var(--bg-dark)",
            }}
        >
            {/* sidebar izquierda: fija el marco del layout para que el main scroll sea independiente */}
            <Sidebar />

            {/* Contenedor principal */}
            <div
                style={{
                    display: "flex",
                    flexDirection: "column",
                    flex: 1,
                    minWidth: 0,
                }}
            >
                {/* Contenido principal */}
                <main
                    className="main-content"
                    style={{
                        flex: 1,
                        padding: "30px 40px",
                        overflowY: "auto",
                    }}
                >
                    <AnimatePresence mode="wait">
                        <motion.div
                            key={location.pathname}
                            initial={{ opacity: 0, y: 16 }}
                            animate={{ opacity: 1, y: 0 }}
                            exit={{ opacity: 0, y: -16 }}
                            transition={{ duration: 0.25, ease: "easeOut" }}
                            style={{ minHeight: "100%" }}
                        >
                            <Outlet />
                        </motion.div>
                    </AnimatePresence>
                </main>

                {/* Footer */}
                <Footer />
            </div>
        </div>
    );
};

export default MainLayout;
