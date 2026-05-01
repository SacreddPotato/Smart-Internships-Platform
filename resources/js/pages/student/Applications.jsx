import { useState, useEffect } from 'react';
import * as applicationApi from '../../api/applicationApi';
import ErrorAlert from '../../components/common/ErrorAlert';
import LoadingSpinner from '../../components/common/LoadingSpinner';
import ApplicationCard from '../../components/applications/ApplicationCard';

export default function Applications() {
    const [applications, setApplications] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        async function loadApplications() {
            try {
                const response = await applicationApi.fetchMine();
                setApplications(response.data.data);
            } catch {
                setError('Could not load your applications.');
            } finally {
                setLoading(false);
            }
        }

        loadApplications();
    }, [])

    return (
        <>
            <div className='page-header'>
                <div>
                    <h1 className='page-title'>My Applications</h1>
                    <p className='page-subtitle'>Track the internships you have applied to.</p>
                </div>
            </div>

            <ErrorAlert message={error} />

            {loading ? <LoadingSpinner label='loading your applications...' /> : (
                <div className='card-grid'>
                    {applications.map((application) => (
                        <ApplicationCard key={application.id} application={application} />
                    ))}
                </div>
            )}
        </>
    );
}
