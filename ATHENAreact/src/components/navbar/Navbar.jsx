import "./Navbar.css";

const Navbar = () => {
    return (
        <nav className="global-navbar">
            {/* barra superior global: busca y accesos rapidos */}
            <div className="global-navbar-search">
                {/* input decorativo: en esta version no hay backend de busqueda */}
                <input type="text" placeholder="Buscar m�dulos, recursos..." />
            </div>

            <div className="global-navbar-links">
                {/* enlaces sin ruta definida: se mantienen como accesos rapidos visuales */}
                <a href="#">Ayuda</a>
                <a href="#">Configuraci�n</a>
            </div>
        </nav>
    );
};

export default Navbar;
