/* eslint-disable react-hooks/set-state-in-effect */
import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import * as internshipApi from "../../api/internshipApi";
import ErrorAlert from "../../components/common/ErrorAlert";
import LoadingSpinner from "../../components/common/LoadingSpinner";

export default function Internships() {
    const [internships, setInternships] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    async function loadInternships() {
        try {
            const response = await internshipApi.fetchMine();
            setInternships(response.data.data);
        } catch {
            setError("Failed to load internships.");
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        loadInternships();
    }, []);

    async function handleArchive(id) {
        await internshipApi.archive(id);
        await loadInternships();
    }

    async function handleDelete(id) {
        if (!window.confirm("Delete this internship?")) return;

        await internshipApi.remove(id);
        await loadInternships();
    }

    return <>
        <div className='page-header'>
            <div>
                <h1 className='page-title'>My Internships</h1>
                <p className='page-subtitle'>Manage opportunities published by your company</p>
            </div>
            <Link to='/company/internships/create' className='btn btn-primary'>Create Internships</Link>
        </div>

        <ErrorAlert message={error} />
        {loading ? <LoadingSpinner label="Loading internships..." /> :  (
            <div className='table-shell'>
                <table className='data-table'>
                    <thead>
                        <tr className='text-center'>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Type</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {internships.map((internship) => (
                            <tr key={internship.id} className='text-center'>
                                <td>{internship.title}</td>
                                <td>{internship.status.charAt(0).toUpperCase() + internship.status.slice(1)}</td>
                                <td>{internship.type.charAt(0).toUpperCase() + internship.type.slice(1)}</td>
                                <td className='row-actions flex-wrap justify-center gap-2'>
                                    <Link to={`/company/internships/${internship.id}/edit`} className='btn btn-seconday'>Edit</Link>
                                    <button className='btn btn-ghost' type='button' onClick={() => handleArchive(internship.id)}>Archive</button>
                                    <button className='btn btn-danger' type='button' onClick={() => handleDelete(internship.id)}>Delete</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        )}
    </>;
}
