import { Link } from 'react-router-dom';

export default function ApplicationCard({ application }) {
    return (
        <div className='internship-card'>
            <h2 className='card-title'>{application.internship.title}</h2>
            <div className='card-meta'>
                <span>{application.status.charAt(0).toUpperCase() + application.status.slice(1)}</span>
                <span>{application.match_score}% match</span>
                <span>{new Date(application.created_at).toLocaleDateString()}</span>
            </div>
            <p className='card-copy mb-4'>{application.message ?? 'No message provided.'}</p>
            <Link className='btn btn-secondary' to={`/internships/${application.internship.id}`}>View internship</Link>
        </div>
    );
}
