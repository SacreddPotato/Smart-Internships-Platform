import { useEffect, useState } from "react";
import { useParams, useNavigate } from "react-router-dom";
import * as internshipApi from "../../api/internshipApi";
import * as skillApi from "../../api/skillApi";
import ErrorAlert from "../../components/common/ErrorAlert";
import LoadingSpinner from "../../components/common/LoadingSpinner";
import InternshipForm from "../../components/common/internships/InternshipForm";

export default function InternshipEdit() {
    const { id } = useParams();
    const navigate = useNavigate();
    const [internship, setInternship] = useState(null);
    const [skills, setSkills] = useState([]);
    const [errors, setErrors] = useState({});
    const [loading, setLoading] = useState(true);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        async function loadFormData() {
            try {
                const [internshipResponse, skillsResponse] = await Promise.all([
                    internshipApi.fetchOne(id),
                    skillApi.fetchAll(),
                ]);

                setInternship(internshipResponse.data.data);
                setSkills(skillsResponse.data.data);
            } catch {
                setError("Failed to load internship data.");
            } finally {
                setLoading(false);
            }
        }

        loadFormData();
    }, [id]);

    async function handleSubmit(formData) {
        setSubmitting(true);
        setErrors({});

        try {
            await internshipApi.update(id, formData);
            navigate("/company/internships");
        } catch (error) {
            if (error.response?.status === 422) {
                setErrors(error.response.data.errors ?? {});
            }
        } finally {
            setSubmitting(false);
        }
    }

    if (loading) return <LoadingSpinner label="Loading internship..." />;
    if (error) return <ErrorAlert message={error} />;

    return (
        <section className="surface">
            <InternshipForm
                key={internship.id}
                initialValues={internship}
                skills={skills}
                errors={errors}
                onSubmit={handleSubmit}
                submitting={submitting}
            />
        </section>
    );
}
