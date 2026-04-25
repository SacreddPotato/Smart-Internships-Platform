import { createBrowserRouter } from "react-router-dom";
import Login from "./components/Login";
import DefaultLayout from "./components/layouts/DefaultLayout";
import GuestLayout from "./components/layouts/GuestLayout";

const router = createBrowserRouter([
    {
        path : "/",
        element: <DefaultLayout />
    },

    {
        path: "/",
        element: <GuestLayout />,
        children: [
            {
                path: "/login",
                element: <Login />
            },

            {
                path: "/register",
                element: <Signup />
            }
        ]
    },
]);

export default router;
