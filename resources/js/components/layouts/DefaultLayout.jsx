import { NavLink, Outlet } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';

function navClass({ isActive }) {
    return isActive ? 'sidebar-link sidebar-link-active' : 'sidebar-link';
}

export default function DefaultLayout() {
    const { user, role, logout } = useAuth();

    return (
        <main className="app-shell">
            <aside className="app-sidebar">
                <div className="app-sidebar-header">
                    <div className="app-logo">Smart Internship</div>
                </div>

                <nav className="sidebar-nav">
                    {role === 'company' && (
                        <>
                            <NavLink to="/company/dashboard" className={navClass}>Dashboard</NavLink>
                            <NavLink to="/company/internships" end className={navClass}>My Internships</NavLink>
                            <NavLink to="/company/internships/archived" className={navClass}>Archived Internships</NavLink>
                            <NavLink to='/company/profile' className={navClass}>Profile</NavLink>
                            <NavLink to='/company/applications' className={navClass}>Applications</NavLink>
                        </>
                    )}
                    {role === 'student' && (
                        <>
                            <NavLink to="/student/dashboard" className={navClass}>Dashboard</NavLink>
                            <NavLink to="/internships" className={navClass}>Browse Internships</NavLink>
                            <NavLink to='/profile' className={navClass}>Profile</NavLink>
                            <NavLink to='/applications' className={navClass}>My Applications</NavLink>
                        </>
                    )}
                    {role === 'admin' && (
                        <>
                            <NavLink to="/admin/dashboard" className={navClass}>Dashboard</NavLink>
                            <NavLink to="/admin/companies" className={navClass}>Manage Companies</NavLink>
                            <NavLink to="/admin/internships" className={navClass}>Manage Internships</NavLink>
                            <NavLink to="/admin/users" className={navClass}>Manage Users</NavLink>
                        </>
                    )}
                </nav>
            </aside>

            <section className="app-main">
                <header className="app-topbar">
                    <div className="topbar-title">
                        <h1>Smart Internship Platform</h1>
                        <p>{user.role.charAt(0).toUpperCase() + user.role.slice(1)}</p>
                    </div>
                    <div className="topbar-actions">
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
