import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import * as internshipApi from "../../api/internships";
import * as skilLApi from "../../api/skills";
import InternshipForm from "../../components/common/internships/InternshipForm";


export default function InternshipCreat() {
    const navigate = useNavigate();
    const [skills, setSkills] = useState([]);
    const [errors, setErrors] = useState({});
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        skilLApi.fetchAll().then((response) => setSkills(response.data.data));
    }, []);

    async function handleSubmit(values) {
        setSubmitting(true);
        setErrors({});

        try {
            await internshipApi.create(values);
            navigate('/company/internships');
        } catch (error) {
            if (error.response?.status === 422) {
                setErrors(error.response.data.errors ?? {});
            }
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <>
            <div className='page-header'>
                <div>
                    <h1 className='page-title'>Create Internship</h1>
                    <p className='page-subtitle'>Publish a new opportunity for students</p>
                </div>
            </div>

            <section className='surface'>
                <InternshipForm skills={skills} onSubmit={handleSubmit} submitting={submitting} errors={errors} />
            </section>
        </>
    )
}
