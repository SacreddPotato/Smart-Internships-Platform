import { Link } from 'react-router-dom';
import MatchScoreBadge from '../../match/MatchScoreBadge';

export default function InternshipCard( { internship }) {
    return (
        <article className='internship-card'>
            <div className='card-main'>
                <h2 className='card-title'>{internship.title}</h2>
                <div className='card-meta flex flex-wrap flex-row items-center mt-1'>
                    <MatchScoreBadge score={internship.match_score} />
                    <span>{internship.company?.company_name ?? 'Unknown company'}</span>
                    <span>{internship.location}</span>
                    <span>{internship.type.charAt(0).toUpperCase() + internship.type.slice(1)}</span>
                </div>
            </div>
            <p className='card-copy'>{internship.description}</p>

            <div className='skill-list'>
                {(internship.skills ?? []).map((skill) => <span className='skill-pill' key={skill.id}>{skill.name}</span>)}
            </div>

            <Link className='btn btn-secondary mt-4' to={`/internships/${internship.id}`}>View details</Link>
        </article>
    );
}
