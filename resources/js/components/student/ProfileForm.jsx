import { useEffect, useState } from 'react';

const emptyProfile = {
    'university': '',
    'major': '',
    'graduation_year': '',
    'gpa': '',
    'bio': ''
}

export default function ProfileForm({ profile, onSubmit, submitting = false, errors = {} }) {
    const [form, setForm] = useState(emptyProfile);

    useEffect(() => {
        if (profile) {
            // The effect should run only once, so we can set the state synchronously without worrying about infinite loops or overwriting state
            // eslint-disable-next-line react-hooks/set-state-in-effect
            setForm({
                university: profile.university ?? '',
                major: profile.major ?? '',
                gpa: profile.gpa ?? '',
                graduation_year: profile.graduation_year ?? '',
                bio: profile.bio ?? ''
            });
        }
    }, [profile])

    function updateField(event) {
        setForm({
            ...form,
            [event.target.name]: event.target.value
        })
    }

    function handleSubmit(event) {
        event.preventDefault();
        onSubmit(form);
    }

    return (
        <form className='form-stack' onSubmit={handleSubmit}>
            <div className='form-grid'>
                <label className='form-group'>
                    <span>University</span>
                    <input className='form-input' name='university' value={form.university} onChange={updateField} />
                    {errors.university && <span className='form-error'>{errors.university}</span>}
                </label>

                <label className='form-group'>
                    <span>Major</span>
                    <input className='form-input' name='major' value={form.major} onChange={updateField} />
                    {errors.major && <span className='form-error'>{errors.major}</span>}
                </label>

                <label className='form-group'>
                    <span>Graduation Year</span>
                    <input className='form-input' name='graduation_year' type='number' value={form.graduation_year} onChange={updateField} />
                    {errors.graduation_year && <span className='form-error'>{errors.graduation_year}</span>}
                </label>

                <label className='form-group'>
                    <span>GPA</span>
                    <input className='form-input' name='gpa' type='number' value={form.gpa} onChange={updateField} />
                    {errors.gpa && <span className='form-error'>{errors.gpa}</span>}
                </label>

                <label className='form-group form-wide'>
                    <span>Bio</span>
                    <textarea className='form-input' name='bio' value={form.bio} onChange={updateField} />
                    {errors.bio && <span className='form-error'>{errors.bio[0]}</span>}
                </label>
            </div>

            <div className='form-actions'>
                <button className='btn btn-primary' type='submit' disabled={submitting}>
                    {submitting ? 'Saving...' : 'Save profile'}
                </button>
            </div>
        </form>
    );
}
