import { Navigate } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';

export default function IndexRedirect() {
    const { isAuthenticated, loading } = useAuth();

    if (loading) {
        return (
        <div className="empty-state">
            <p className="empty-state-title">Loading...</p>
            <p className="empty-state-copy">Checking your session.</p>
        </div>
        );
    }

    return isAuthenticated ? <Navigate to="/dashboard" /> : <Navigate to="/login" />;
}


