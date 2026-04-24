import { useEffect, useState } from 'react';
import api from './api/axios';

const initialApiState = {
    status: 'checking',
    message: 'Checking API connection',
};

export default function App() {
    const [apiState, setApiState] = useState(initialApiState);

    useEffect(() => {
        let cancelled = false;

        api.get('/health')
            .then((response) => {
                if (cancelled) {
                    return;
                }

                setApiState({
                    status: 'online',
                    message: response.data.message ?? 'API is online',
                });
            })
            .catch(() => {
                if (cancelled) {
                    return;
                }

                setApiState({
                    status: 'offline',
                    message: 'API connection failed',
                });
            });

        return () => {
            cancelled = true;
        };
    }, []);

    const statusClasses = {
        checking: 'border-slate-300 bg-slate-50 text-slate-700',
        online: 'border-emerald-300 bg-emerald-50 text-emerald-700',
        offline: 'border-rose-300 bg-rose-50 text-rose-700',
    };

    return (
        <main className="min-h-screen bg-zinc-50 text-zinc-950">
            <section className="mx-auto flex min-h-screen w-full max-w-5xl flex-col justify-center px-6 py-12">
                <div className="max-w-2xl">
                    <p className="mb-3 text-sm font-semibold uppercase tracking-wide text-teal-700">
                        Phase 0
                    </p>
                    <h1 className="text-4xl font-bold leading-tight text-zinc-950 sm:text-5xl">
                        Smart Internship Matching Platform
                    </h1>
                    <p className="mt-5 max-w-xl text-base leading-7 text-zinc-600">
                        Laravel and React are running from one project root, with the frontend calling the versioned API through the shared Vite setup.
                    </p>
                </div>

                <div className="mt-10 grid gap-4 sm:grid-cols-3">
                    <StatusCard label="Project" value="Unified" detail="Laravel + React" />
                    <StatusCard label="Frontend" value="React" detail="resources/js" />
                    <StatusCard
                        label="API"
                        value={apiState.status}
                        detail={apiState.message}
                        className={statusClasses[apiState.status]}
                    />
                </div>
            </section>
        </main>
    );
}

function StatusCard({ label, value, detail, className = 'border-zinc-200 bg-white text-zinc-800' }) {
    return (
        <article className={`rounded-lg border p-5 shadow-sm ${className}`}>
            <p className="text-xs font-semibold uppercase tracking-wide opacity-75">{label}</p>
            <p className="mt-3 text-2xl font-bold capitalize">{value}</p>
            <p className="mt-2 text-sm opacity-80">{detail}</p>
        </article>
    );
}
