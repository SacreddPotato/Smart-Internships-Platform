import React from 'react';
import { createRoot } from 'react-dom/client';
import '../css/app.css';
import { AuthProvider } from './contexts/AuthContext.jsx';
import { RouterProvider } from 'react-router-dom';
import router from './router/index.jsx';

createRoot(document.getElementById('root')).render(
    <React.StrictMode>
        <AuthProvider>
            <RouterProvider router={router} />
        </AuthProvider>
    </React.StrictMode>,
);
