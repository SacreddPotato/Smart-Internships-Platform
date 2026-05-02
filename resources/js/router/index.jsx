import { createBrowserRouter } from "react-router-dom";
import DefaultLayout from "../components/layouts/DefaultLayout";
import GuestLayout from "../components/layouts/GuestLayout";
import IndexRedirect from "../components/common/IndexRedirect";
import GuestOnlyRoute from "../components/common/GuestOnlyRoute";
import ProtectedRoute from "../components/common/ProtectedRoute";
import RoleRoute from "../components/common/RoleRoute";
import Login from "../pages/auth/Login";
import Register from "../pages/auth/Register";
import Dashboard from "../pages/Dashboard";
import Forbidden from "../pages/errors/Forbidden";
import Browse from "../pages/internships/Browse";
import Detail from "../pages/internships/Detail";
import Internships from "../pages/company/Internships";
import InternshipCreate from "../pages/company/InternshipCreate";
import InternshipEdit from "../pages/company/InternshipEdit";
import ArchivedInternships from "../pages/company/ArchivedInternships";
import Profile from "../pages/student/Profile";
import Applications from "../pages/student/Applications";
import Recommendations from "../pages/student/Recommendations";

const router = createBrowserRouter([
    {
        // Redirect root to dashboard
        path: "/",
        element: <IndexRedirect />,
    },
    {
        // Signup/Login routes
        element: <GuestOnlyRoute />,
        children: [
            {
                element: <GuestLayout />,
                children: [
                    {
                        path: "/login",
                        element: <Login />,
                    },
                    {
                        path: "/register",
                        element: <Register />,
                    },
                ],
            },
        ],
    },
    {
        element: <ProtectedRoute />,
        children: [
            {
                element: <DefaultLayout />,
                children: [
                    // Company routes
                    {
                        element: <RoleRoute allowedRoles={["company"]} />,
                        path: "/company",
                        children: [
                            {
                                path: "dashboard",
                                element: <Dashboard />,
                            },
                            {
                                path: "internships",
                                element: <Internships />,
                            },
                            {
                                path: "internships/create",
                                element: <InternshipCreate />,
                            },
                            {
                                path: "internships/:id/edit",
                                element: <InternshipEdit />,
                            },
                            {
                                path: "internships/archived",
                                element: <ArchivedInternships />,
                            },
                        ],
                    },
                    //Student Routes
                    {
                        element: <RoleRoute allowedRoles={["student"]} />,
                        children: [
                            {
                                path: "/student/dashboard",
                                element: <Dashboard />,
                            },
                            {
                                path: "/student/profile",
                                element: <Profile />,
                            },
                            {
                                path: "/internships",
                                element: <Browse />,
                            },
                            {
                                path: "/internships/:id",
                                element: <Detail />,
                            },
                            {
                                path: '/student/applications',
                                element: <Applications />,
                            },
                            {
                                path: '/student/recommendations',
                                element: <Recommendations />
                            }
                        ],
                    },
                    // Common routes
                    {
                        path: "/403",
                        element: <Forbidden />,
                    },
                ],
            },
        ],
    },
]);

export default router;
