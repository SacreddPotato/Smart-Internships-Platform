import { useEffect, useState } from "react";
import * as internshipApi from "../../api/internshipApi";
import ErrorAlert from "../../components/common/ErrorAlert";
import LoadingSpinner from "../../components/common/LoadingSpinner";

export default function ArchivedInternships() {
    const [internships, setInternships] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        async function loadArchivedInternships() {
            try {
                const response = await internshipApi.fetchArchived();
                setInternships(response.data.data);
            } catch {
                setError("Failed to load archived internships.");
            } finally {
                setLoading(false);
            }
        }
        loadArchivedInternships();
    }, []);

    return (
        <>
            <div className="page-header">
                <div>
                    <h1 className="page-title">Archived Internships</h1>
                    <p className="page-subtitle">Review opportunities your company has archived.</p>
                </div>
            </div>

            <ErrorAlert message={error} />
            {loading ? <LoadingSpinner /> : (
                <div className="card-grid">
                    {internships.map((internship) => (
                        <div className="internship-card" key={internship.id}>
                            <h2 className="card-title">{internship.title}</h2>
                            <div className="card-meta">
                                <span>{internship.type}</span>
                                <span>{internship.archived_at ?? internship.updated_at}</span>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </>
    );
}
