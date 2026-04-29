import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';

export default function RoleRoute( { allowedRoles }) {
    const { user, loading } = useAuth();

    if (loading) {
        return (
        <div className="empty-state">
            <p className="empty-state-title">Loading...</p>
            <p className="empty-state-copy">Checking your session.</p>
        </div>
        );
    }

    return user && allowedRoles.includes(user.role) ? <Outlet /> : <Navigate to="/403" />;
}
