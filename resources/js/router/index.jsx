import { createBrowserRouter } from "react-router-dom";
import Login from "../pages/auth/Login";
import Register from "../pages/auth/Register";
import Dashboard from "../pages/Dashboard";
import Forbidden from "../pages/errors/Forbidden";
import DefaultLayout from "../components/layouts/DefaultLayout";
import GuestLayout from "../components/layouts/GuestLayout";
import IndexRedirect from "../components/common/IndexRedirect";
import GuestOnlyRoute from "../components/common/GuestOnlyRoute";
import ProtectedRoute from "../components/common/ProtectedRoute";

const router = createBrowserRouter([
    {
        path : "/",
        element: <IndexRedirect />
    },
    {
        element: <GuestOnlyRoute />,
        children: [
            {
                element: <GuestLayout />,
                children: [
                    {
                        path: "/login",
                        element: <Login />
                    },
                    {
                        path: "/register",
                        element: <Register />
                    }
                ]
            }
        ]
    },
    {
        element: <ProtectedRoute />,
        children: [
            {
                element: <DefaultLayout />,
                children: [
                    {
                        path: "/dashboard",
                        element: <Dashboard />
                    },
                    // {
                    //     path: "/internships",
                    //     element: <Browse />
                    // },
                    // {
                    //     path: "/internships/:id",
                    //     element: <Detail />
                    // },
                    {
                        path: "/403",
                        element: <Forbidden />
                    }
                ]
            }
        ]
    }
]);

export default router;
