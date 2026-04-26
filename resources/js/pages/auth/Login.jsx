import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';

export default function Login() {
    const [form, setForm] = useState({
        email: '',
        password: '',
    });
    const [errors, setErrors] = useState({});
    const [submitting, setSubmitting] = useState(false);

    const { login } = useAuth();
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
            await login(form);
            navigate('/dashboard');
        } catch (error) {
            if (error.response?.status === 422) {
                setErrors(error.response.data.errors);
            } else {
                setErrors({
                    form: ['Unable to sign in. Please check your credentials.'],
                });
            }
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <div className="auth-card">
            <h1 className="auth-title">Welcome back</h1>
            <p className="auth-subtitle">Sign in to continue.</p>

            {errors.form && (
                <div className="alert alert-error">{errors.form[0]}</div>
            )}

            <form className="form-stack" onSubmit={handleSubmit}>
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
                        autoComplete="current-password"
                    />
                    {errors.password && (
                        <p className="form-error">{errors.password[0]}</p>
                    )}
                </div>

                <button
                    className="btn btn-primary"
                    type="submit"
                    disabled={submitting}
                >
                    {submitting ? 'Signing in...' : 'Sign in'}
                </button>

                <p className="auth-switch">
                    Don&apos;t have an account?{' '}
                    <Link className="auth-switch-link" to="/register">
                        Sign up
                    </Link>
                </p>
            </form>
        </div>
    );
}
