import "./Header.css";

const Header = () => {
    return (
        <header className="global-header">
            <div className="global-header-content">
                <h1>Panel de Control - ATHENA</h1>
                <div className="global-user-profile">
                    <span className="global-notifications">??</span>
                    <div className="global-user-info">
                        <p className="global-user-name">Usuario Estudiante</p>
                        <p className="global-user-role">ADSO - SENA</p>
                    </div>
                    <img
                        src="/img/user-avatar.png"
                        alt="Avatar"
                        className="global-avatar"
                    />
                </div>
            </div>
        </header>
    );
};

export default Header;
