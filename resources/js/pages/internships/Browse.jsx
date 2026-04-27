import { useEffect, useState } from 'react';
import * as internshipApi from '../../api/internshipApi';
import ErrorAlert from '../../components/common/ErrorAlert';
import LoadingSpinner from '../../components/common/LoadingSpinner'
import Pagination from '../../components/common/Pagination';
import InternshipFilters from '../../components/common/internships/InternshipFilters';
import InternshipList from '../../components/common/internships/InternshipList';

export default function Browse() {
    const [internships, setInternships] = useState([]);
    const [meta, setMeta] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [filters, setFilters] = useState({
        terms: [],
        match: 'any',
        type: '',
        page: 1,
    });
    const [debouncedFilters, setDebouncedFilters] = useState(filters);

    useEffect(() => {
      const timeoutId = setTimeout(() => {
        setDebouncedFilters(filters);
      }, 300)

      return () => {
        clearTimeout(timeoutId);
      }
    }, [filters]);


    useEffect(() => {
        async function loadInternships() {
            setLoading(true);
            setError(null);

            try {
                const response = await internshipApi.fetchAll(debouncedFilters);
                setInternships(response.data.data);
                setMeta(response.data.meta);
            } catch {
                setError('Failed to fetch internships')
            } finally {
                setLoading(false);
            }
        }

        loadInternships();
    }, [debouncedFilters]);

    return (
        <>
            <div className='page-header'>
                <div>
                    <h1 className='page-title'>Browse Internships</h1>
                    <p className='page-subtitle'>Find open opportunities by role, company, location and work type.</p>
                </div>
            </div>

            <InternshipFilters filters={filters} onChange={setFilters} />
            <ErrorAlert message={error} />
            {loading ? (
                <LoadingSpinner label='Loading internships...' />
            ) : (
                <>
                    <InternshipList internships={internships} />
                    <Pagination meta={meta} onPageChange={(page) => setFilters((current) => ({...current, page}))} />
                </>
            )}
        </>
    )
}
