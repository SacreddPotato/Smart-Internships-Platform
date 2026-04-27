import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import * as internshipApi from '../../api/internshipApi';
import ErrorAlert from '../../components/common/ErrorAlert';
import LoadingSpinner from '../../components/common/LoadingSpinner'

export default function Detail() {
    const { id } = useParams();
    const [internship, setInternship] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        async function LoadInternship() {
            setLoading(true);
            setError(null);

            try {
                const response = await internshipApi.fetchOne(id);
                setInternship(response.data.data);
            } catch {
                setError('fCould not load internship details.')
            } finally {
                setLoading(false);
            }
        }

        LoadInternship();
    }, [id]);

    if (loading) {
        return <LoadingSpinner label='Loading internship details...' />
    }

    if (error) {
        return <ErrorAlert message={error} />
    }

    if (!internship) {
        return null;
    }

    return (
      <div className='detail-layout'>
        <main className='detail-main'>
            <section className='surface'>
                <h1 className='page-title'>{internship.title}</h1>
                <p className='page-subtitle'>{internship.company?.company_name ?? 'Unknown company'}</p>
                <div className='card-meta'>
                    <span>{internship.location}</span>
                    <span>{(internship.type === 'onsite') ? 'On-site' : internship.type.charAt(0).toUpperCase() + internship.type.slice(1)}</span>
                    <span>{internship.status.charAt(0).toUpperCase() + internship.status.slice(1)}</span>
                </div>
            </section>

            <section className='surface'>
                <h2 className='section-title'>Description</h2>
                <p className='section-copy'>{internship.description}</p>
            </section>

            <section className='surface'>
                <h2 className='section-title'>Requirements</h2>
                <p className='section-copy'>{internship?.requirements ?? 'No requirements specified.'}</p>
            </section>

            <aside className='detail-sidebar surface-muted'>
                <h2 className='section-title'>Skills</h2>
                <div className='skills-list flex flex-wrap gap-2'>
                    {(internship.skills ?? []).map((skill) => (<span className='skill-pill' key={skill.id}>{skill.name}</span>))}
                </div>
            </aside>
        </main>
      </div>
    );
}
