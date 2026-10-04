import { createBrowserRouter, Navigate } from 'react-router-dom'
import { NotFound } from '@/components/feedback/NotFound'
import { LoginPage } from '@/features/auth/pages/LoginPage'
import { RegisterPage } from '@/features/auth/pages/RegisterPage'
import { VerifyRegistrationPage } from '@/features/auth/pages/VerifyRegistrationPage'
import { GuestOnly, RequireAuth } from '@/features/auth/route-guards'
import { ProjectDetailPage } from '@/features/projects/pages/ProjectDetailPage'
import { ProjectsPage } from '@/features/projects/pages/ProjectsPage'
import { AppLayout } from '@/layouts/AppLayout'
import { AuthLayout } from '@/layouts/AuthLayout'

export const router = createBrowserRouter([
    {
        element: <GuestOnly />,
        children: [
            {
                element: <AuthLayout />,
                children: [
                    { path: '/login', element: <LoginPage /> },
                    { path: '/register', element: <RegisterPage /> },
                    { path: '/register/verify', element: <VerifyRegistrationPage /> },
                ],
            },
        ],
    },
    {
        element: <RequireAuth />,
        children: [
            {
                element: <AppLayout />,
                children: [
                    { index: true, element: <Navigate to="/projects" replace /> },
                    { path: '/projects', element: <ProjectsPage /> },
                    { path: '/projects/:projectId', element: <ProjectDetailPage /> },
                ],
            },
        ],
    },
    { path: '*', element: <NotFound /> },
])