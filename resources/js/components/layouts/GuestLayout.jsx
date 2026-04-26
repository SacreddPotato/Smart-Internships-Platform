import { Outlet } from 'react-router-dom';

export default function GuestLayout() {
  return (
    <main className="auth-shell">
            <section className="auth-brand-panel">
                <div>
                    <div className="auth-brand-mark">Smart Internship</div>
                    <h1 className="auth-brand-title">Find the right internship path.</h1>
                    <p className="auth-brand-copy">
                        Students, companies, and admins work from one guided platform.
                    </p>
                </div>
                <div className="auth-brand-note">
                    Build your account first, then continue into the platform.
                </div>
            </section>

            <section className="auth-form-panel">
                <Outlet />
            </section>
        </main>
  );
}

