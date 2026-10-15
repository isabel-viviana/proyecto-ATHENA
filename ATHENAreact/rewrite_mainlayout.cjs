const fs = require("fs");
const path = require("path");

const content = `import { Outlet } from 'react-router-dom';
import Sidebar from './Sidebar';
import Header from './header/Header';
import Navbar from './navbar/Navbar';
import Fooder from './fooder/Fooder';

const MainLayout = () => {
  return (
    <div className="layout" style={{ display: 'flex', minHeight: '100vh', backgroundColor: 'var(--bg-dark)' }}>
      {/* Sidebar fijo a la izquierda */}
      <Sidebar />
      
      {/* Contenedor derecho para el resto de elementos */}
      <div style={{ display: 'flex', flexDirection: 'column', flex: 1, minWidth: 0 }}>
        
        {/* Cabeceras (Header y Navbar) */}
        <Header />
        <Navbar />
        
        {/* Contenido principal de la página */}
        <main className="main-content" style={{ flex: 1, padding: '30px 40px', overflowY: 'auto' }}>
          <Outlet />
        </main>
        
        {/* Footer al final */}
        <Fooder />
      </div>
    </div>
  );
};

export default MainLayout;
`;

fs.writeFileSync(
    path.join(__dirname, "src", "components", "MainLayout.jsx"),
    content,
    "utf8",
);
console.log("MainLayout written explicitly as UTF-8");
