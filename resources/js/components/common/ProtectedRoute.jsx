import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';

export default function ProtectedRoute() {
    const { isAuthenticated, loading } = useAuth();

    if (loading) {
        return (
        <div className="empty-state">
            <p className="empty-state-title">Loading...</p>
            <p className="empty-state-copy">Checking your session.</p>
        </div>
        );
    }

    return isAuthenticated ? <Outlet /> : <Navigate to="/login" />;
}
