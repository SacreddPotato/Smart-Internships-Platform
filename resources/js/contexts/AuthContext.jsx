import { createContext, useContext, useEffect, useMemo, useState } from "react";
import * as authApi from "../api/authApi";

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [token, setToken] = useState(
        () => localStorage.getItem("token") || null,
    );
    const [loading, setLoading] = useState(() => !!localStorage.getItem("token"));
    const [error, setError] = useState(null);

    useEffect(() => {
        if (!token) {
            return;
        }

        let cancelled = false;

        authApi
            .me()
            .then((response) => {
                if (!cancelled) {
                    setUser(response.data.user);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    localStorage.removeItem("token");
                    setToken(null);
                    setUser(null);
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            }
        );

        return () => {
            cancelled = true;
        };
    }, [token]);

    async function register(payload) {
        setError(null);

        return await authApi.register(payload);
    }

    async function login(payload) {
        setError(null);
        const response = await authApi.login(payload);
        const nextToken = response.data.token;

        if (!nextToken) {
            setError("No token received");
            return;
        }

        localStorage.setItem("token", nextToken);
        setToken(nextToken);
        setUser(response.data.user);

        return response;
    }

    async function logout() {
        try {
            await authApi.logout();
        } finally {
            localStorage.removeItem("token");
            setToken(null);
            setUser(null);
        }
    }

    const value = useMemo(
        () => ({
            user,
            role: user?.role ?? null,
            token,
            loading,
            error,
            isAuthenticated: !!user && !!token,
            register,
            login,
            logout,
        }),
        [user, token, loading, error],
    );

    return (
        <AuthContext.Provider value={value}>
            {children}
        </AuthContext.Provider>
    )
}

    export function useAuth() {
        const context = useContext(AuthContext);

        if (!context) {
            throw new Error("useAuth must be used within an AuthProvider");
        }

        return context;
}
