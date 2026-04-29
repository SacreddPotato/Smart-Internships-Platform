import { Navigate } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';

export default function IndexRedirect() {
    const { role, isAuthenticated, loading } = useAuth();

    if (loading) {
        return (
        <div className="empty-state">
            <p className="empty-state-title">Loading...</p>
            <p className="empty-state-copy">Checking your session.</p>
        </div>
        );
    }

    if (! isAuthenticated) {
        return <Navigate to="/login" replace />;
    }

    if (! role) {
        return <Navigate to="/login" replace />;
    }

    switch (role) {
        case 'company':
            return <Navigate to='/company/dashboard' replace />;

        case 'student':
            return <Navigate to='/student/dashboard' replace />;

        case 'admin':
            return <Navigate to='/admin/dashboard' replace />;

        default:
            return <Navigate to="/login" replace />;
    }
}


