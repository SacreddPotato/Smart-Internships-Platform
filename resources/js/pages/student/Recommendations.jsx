import { useEffect, useState } from 'react';
import * as matchApi from '../../api/matchApi';
import ErrorALert from '../../components/common/ErrorAlert';
import LoadingSpinner from '../../components/common/LoadingSpinner';
import InternshipList from '../../components/common/internships/InternshipList';

export default function Recommendations() {
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [internships, setInternships] = useState([]);

    useEffect(() => {
        async function loadRecommendations() {
            try {
                const response = await matchApi.fetchRecommendations();
                setInternships(response.data.data);
            } catch {
                setError('Failed to load recommendations. Please try again later.');
            } finally {
                setLoading(false);
            }
        }

        loadRecommendations();
    }, []);

    return (
        <>
            <div className='page-header'>
                <div>
                    <h1 className='page-title'>Recommendations</h1>
                    <p className='page-subtitle'>Here are some internships that might interest you</p>
                </div>
            </div>

            <ErrorALert message={error} />
            {loading ? <LoadingSpinner /> : <InternshipList internships={internships} />}
        </>
    );
}
