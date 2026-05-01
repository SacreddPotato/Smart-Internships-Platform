import { useState } from 'react';

const emptyValues = {
    title: '',
    description: '',
    requirements: '',
    location: '',
    type: 'remote',
    starts_at: '',
    ends_at: '',
    skills: [],
};

// Normalizes API internship data to match form state shape, especially for skills which are stored as objects in the API but we want to manage as an array of IDs in the form.
function normalizeValues(values) {
    if (!values) return emptyValues;

    return {
        ...emptyValues,
        ...values,
        skills: values.skills?.map(s => s.id) ?? []
    }
}

export default function InternshipForm({ initialValues, skills = [], onSubmit, submitting = false, errors = {} }) {
    const [form, setForm] = useState(() => normalizeValues(initialValues));

    function updateField(event) {
        setForm({
            ...form,
            [event.target.name]: event.target.value
        });
    }

    // Toggle skill selection in the form state and avoids duplicates
    function toggleSkill(skillId) {
        setForm((current) => ({
            ...current,
            skills: current.skills.includes(skillId) ? current.skills.filter(id => id !== skillId) : [...current.skills, skillId]
        }))
    }

    function handleSubmit (event) {
        event.preventDefault();
        onSubmit(form);
    }

    return (
        <form className="form-stack" onSubmit={handleSubmit}>
            <div className="form-grid">
                <label className="form-group">
                    <span>Title</span>
                    <input className="form-input" name="title" value={form.title} onChange={updateField} />
                    {errors.title && <span className="form-error">{errors.title[0]}</span>}
                </label>

                <label className="form-group">
                    <span>Location</span>
                    <input className="form-input" name="location" value={form.location} onChange={updateField} />
                    {errors.location && <span className="form-error">{errors.location[0]}</span>}
                </label>

                <label className="form-group">
                    <span>Type</span>
                    <select className="form-select" name="type" value={form.type} onChange={updateField}>
                        <option value="remote">Remote</option>
                        <option value="onsite">Onsite</option>
                        <option value="hybrid">Hybrid</option>
                    </select>
                    {errors.type && <span className="form-error">{errors.type[0]}</span>}
                </label>

                <label className="form-group">
                    <span>Starts at</span>
                    <input className="form-input" type="date" name="starts_at" value={form?.starts_at ?? ''} onChange={updateField} />
                    {errors.starts_at && <span className="form-error">{errors.starts_at[0]}</span>}
                </label>

                <label className="form-group">
                    <span>Ends at</span>
                    <input className="form-input" type="date" name="ends_at" value={form?.ends_at ?? ''} onChange={updateField} />
                    {errors.ends_at && <span className="form-error">{errors.ends_at[0]}</span>}
                </label>

                <label className="form-group form-wide">
                    <span>Description</span>
                    <textarea className="form-textarea" name="description" value={form.description} onChange={updateField} />
                    {errors.description && <span className="form-error">{errors.description[0]}</span>}
                </label>

                <label className="form-group form-wide">
                    <span>Requirements</span>
                    <textarea className="form-textarea" name="requirements" value={form?.requirements ?? ''} onChange={updateField} />
                    {errors.requirements && <span className="form-error">{errors.requirements[0]}</span>}
                </label>
            </div>

            <fieldset className="form-group">
                <legend>Skills</legend>
                <div className="skill-toggle-list">
                    {skills.map((skill) => (
                        <label
                            className={`skill-toggle ${form.skills.includes(skill.id) ? 'skill-toggle-active' : ''}`}
                            key={skill.id}
                        >
                            <input
                                className="skill-toggle-input"
                                type="checkbox"
                                checked={form.skills.includes(skill.id)}
                                onChange={() => toggleSkill(skill.id)}
                            />
                            <span>{skill.name}</span>
                        </label>
                    ))}
                </div>
                {errors.skills && <span className="form-error">{errors.skills[0]}</span>}
            </fieldset>

            <div className="form-actions">
                <button className="btn btn-primary" type="submit" disabled={submitting}>
                    {submitting ? 'Saving...' : 'Save internship'}
                </button>
            </div>
        </form>
    )
}
