import { Link } from 'react-router-dom';

export default function Forbidden() {
    return (
        <div className="empty-state">
            <div className="empty-state-icon">403</div>

            <h1 className="empty-state-title">Access denied</h1>

            <p className="empty-state-copy">
                You are signed in, but your account does not have permission to
                view this page.
            </p>

            <Link to="/" className="btn btn-primary">
                Back to dashboard
            </Link>
        </div>
    );
}
