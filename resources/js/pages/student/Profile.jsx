import {useEffect, useState} from "react";
import * as skillApi from "../../api/skillApi";
import * as studentApi from "../../api/studentApi";
import ErrorAlert from "../../components/common/ErrorAlert";
import FileUpload from "../../components/common/FileUpload";
import LoadingSpinner from "../../components/common/LoadingSpinner";
import SkillSelector from "../../components/common/SkillSelector";
import ProfileForm from "../../components/student/ProfileForm";

export default function Profile() {
    const [profile, setProfile] = useState(null);
    const [skills, setSkills] = useState([]);
    const [selectedSkills, setSelectedSkills] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [errors, setErrors] = useState({});
    const [submitting, setSubmitting] = useState(false);
    const [savingSkills, setSavingSkills] = useState(false);

    async function loadProfile() {
        setLoading(true);

        try {
            const [profileResponse, skillsresponse] = await Promise.all([
                studentApi.fetchProfile(),
                skillApi.fetchAll()
            ]);

            setProfile(profileResponse.data.data);
            setSkills(skillsresponse.data.data);
            setSelectedSkills(profileResponse.data.data.skills?.map(skill => skill.id) ?? []);
        } catch {
            setError('Could not load your profile');
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        // Should only run once, so we can set the state synchronously without worrying about infinite loops or overwriting state
        // eslint-disable-next-line react-hooks/set-state-in-effect
        loadProfile();
    }, []);

    async function handleProfileSubmit(values) {
        setSubmitting(true);
        setErrors({});

        try {
            const response = await studentApi.updateProfile(values);
            setProfile(response.data.data);
        } catch (error) {
            if (error.response?.status === 422) {
                setErrors(error.response.data.errors);
            }
        } finally {
            setSubmitting(false);
        }
    }

    async function handleSkilLSave() {
        setSavingSkills(true);

        try {
            const response = await studentApi.syncSkills(selectedSkills);
            setProfile(response.data.data);
        } catch {
            setError('Failed to save skills');
        } finally {
            setSavingSkills(false);
        }

    }

    async function handleCvUpload(file) {
        const response = await studentApi.uploadCv(file);
        setProfile(response.data.data);
    }

    if (loading) return <LoadingSpinner label="Loading profile..." />

     return (
        <>
            <div className="page-header">
                <div>
                    <h1 className="page-title">Student Profile</h1>
                    <p className="page-subtitle">Keep your academic details, skills, and CV up to date.</p>
                </div>
            </div>

            <ErrorAlert message={error} />

            <div className="detail-layout">
                <main className="detail-main">
                    <section className="surface">
                        <ProfileForm profile={profile} onSubmit={handleProfileSubmit} submitting={submitting} errors={errors} />
                    </section>

                    <section className="surface">
                        <h2 className="section-title">Skills</h2>
                        <SkillSelector skills={skills} selectedIds={selectedSkills} onChange={setSelectedSkills} />
                        <button className="btn btn-primary" type="button" onClick={handleSkilLSave} disabled={savingSkills || submitting}>
                            {savingSkills || submitting ? 'Saving...' : 'Save Skills'}
                        </button>
                    </section>
                </main>

                <aside className="detail-sidebar surface-muted">
                    <h2 className="section-title">CV</h2>
                    {profile?.cv_url && <a href={profile.cv_url} target="_blank" rel="noreferrer">View current CV</a>}
                    <FileUpload accept="application/pdf" onUpload={handleCvUpload} />
                </aside>
            </div>
        </>
    );
}
