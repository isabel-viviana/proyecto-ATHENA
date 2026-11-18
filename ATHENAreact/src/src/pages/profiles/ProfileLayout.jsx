import { Outlet } from "react-router-dom";

const ProfileLayout = () => {
    return (
        <div className="profile-layout">
            {/* shared profile header / tabs could go here */}
            <Outlet />
        </div>
    );
};

export default ProfileLayout;
