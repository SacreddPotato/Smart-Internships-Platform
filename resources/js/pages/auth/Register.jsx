import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';

export default function Register() {
    const [form, setForm] = useState({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: 'student',
    });
    const [errors, setErrors] = useState({});
    const [submitting, setSubmitting] = useState(false);

    const { register } = useAuth();
    const navigate = useNavigate();

    function handleChange(event) {
        setForm({
            ...form,
            [event.target.name]: event.target.value,
        });
    }

    async function handleSubmit(event) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});

        try {
            await register(form);
            navigate('/login');
        } catch (error) {
            if (error.response?.status === 422) {
                setErrors(error.response.data.errors);
            } else {
                setErrors({
                    form: ['Unable to create your account. Please try again.'],
                });
            }
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <div className="auth-card">
            <h1 className="auth-title">Create your account</h1>
            <p className="auth-subtitle">
                Choose your role and enter your details.
            </p>

            {errors.form && (
                <div className="alert alert-error">{errors.form[0]}</div>
            )}

            <form className="form-stack" onSubmit={handleSubmit}>
                <div className="form-group">
                    <label className="form-label" htmlFor="name">
                        Name
                    </label>
                    <input
                        id="name"
                        name="name"
                        type="text"
                        className="form-input"
                        value={form.name}
                        onChange={handleChange}
                        autoComplete="name"
                    />
                    {errors.name && (
                        <p className="form-error">{errors.name[0]}</p>
                    )}
                </div>

                <div className="form-group">
                    <label className="form-label" htmlFor="email">
                        Email
                    </label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        className="form-input"
                        value={form.email}
                        onChange={handleChange}
                        autoComplete="email"
                    />
                    {errors.email && (
                        <p className="form-error">{errors.email[0]}</p>
                    )}
                </div>

                <div className="form-group">
                    <label className="form-label" htmlFor="password">
                        Password
                    </label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        className="form-input"
                        value={form.password}
                        onChange={handleChange}
                        autoComplete="new-password"
                    />
                    {errors.password && (
                        <p className="form-error">{errors.password[0]}</p>
                    )}
                </div>

                <div className="form-group">
                    <label
                        className="form-label"
                        htmlFor="password_confirmation"
                    >
                        Confirm password
                    </label>
                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        className="form-input"
                        value={form.password_confirmation}
                        onChange={handleChange}
                        autoComplete="new-password"
                    />
                </div>

                <div className="form-group">
                    <label className="form-label" htmlFor="role">
                        Account type
                    </label>
                    <select
                        id="role"
                        name="role"
                        className="form-select"
                        value={form.role}
                        onChange={handleChange}
                    >
                        <option value="student">Student</option>
                        <option value="company">Company</option>
                    </select>
                    {errors.role && (
                        <p className="form-error">{errors.role[0]}</p>
                    )}
                </div>

                <button
                    type="submit"
                    className="btn btn-primary"
                    disabled={submitting}
                >
                    {submitting ? 'Creating account...' : 'Create account'}
                </button>

                <p className="auth-switch">
                    Already have an account?{' '}
                    <Link className="auth-switch-link" to="/login">
                        Sign in
                    </Link>
                </p>
            </form>
        </div>
    );
}
