import { NavLink, Outlet } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';

function navClass({ isActive }) {
    return isActive ? 'sidebar-link sidebar-link-active' : 'sidebar-link';
}

export default function DefaultLayout() {
    const { user, logout } = useAuth();

    return (
        <main className="app-shell">
            <aside className="app-sidebar">
                <div className="app-sidebar-header">
                    <div className="app-logo">Smart Internship</div>
                </div>

                <nav className="sidebar-nav">
                    <NavLink to="/dashboard" className={navClass}>Dashboard</NavLink>
                    <NavLink to="/internships" className={navClass}>Internships</NavLink>
                    <NavLink to="/student/profile" className={navClass}>Profile</NavLink>
                    <NavLink to="/student/applications" className={navClass}>Applications</NavLink>
                </nav>
            </aside>

            <section className="app-main">
                <header className="app-topbar">
                    <div className="topbar-title">
                        <h1>Smart Internship Platform</h1>
                        <p>{user.role.charAt(0).toUpperCase() + user.role.slice(1)}</p>
                    </div>
                    <div className="topbar-actions">
                        <span className="user-chip">{user?.name}</span>
                        <button className="btn btn-ghost" onClick={logout}>Logout</button>
                    </div>
                </header>

                <div className="content-shell">
                    <Outlet />
                </div>
            </section>
        </main>
    );
}
