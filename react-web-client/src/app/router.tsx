import { createBrowserRouter, Navigate } from 'react-router-dom'
import { ProjectActivityPage } from '@/features/activity/pages/ProjectActivityPage'
import { NotFound } from '@/components/feedback/NotFound'
import { ForgotPasswordPage } from '@/features/auth/pages/ForgotPasswordPage'
import { LoginPage } from '@/features/auth/pages/LoginPage'
import { RegisterPage } from '@/features/auth/pages/RegisterPage'
import { ResetPasswordPage } from '@/features/auth/pages/ResetPasswordPage'
import { VerifyRegistrationPage } from '@/features/auth/pages/VerifyRegistrationPage'
import { GuestOnly, RequireAuth } from '@/features/auth/route-guards'
import { DailySummaryPage } from '@/features/daily-summary/pages/DailySummaryPage'
import { InvitationPage } from '@/features/invitations/pages/InvitationPage'
import { ProjectInvitationsPage } from '@/features/invitations/pages/ProjectInvitationsPage'
import { ReceivedInvitationsPage } from '@/features/invitations/pages/ReceivedInvitationsPage'
import { ProjectMembersPage } from '@/features/members/pages/ProjectMembersPage'
import { ProfilePage } from '@/features/profile/pages/ProfilePage'
import { ProjectDetailPage } from '@/features/projects/pages/ProjectDetailPage'
import { ProjectsPage } from '@/features/projects/pages/ProjectsPage'
import { ProjectTasksPage } from '@/features/tasks/pages/ProjectTasksPage'
import { TaskDetailPage } from '@/features/tasks/pages/TaskDetailPage'
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
                    { path: '/forgot-password', element: <ForgotPasswordPage /> },
                    { path: '/password-reset/:token', element: <ResetPasswordPage /> },
                ],
            },
        ],
    },
    {
        // Tautan di email undangan: dibuka dengan atau tanpa login, jadi tidak dibungkus GuestOnly/RequireAuth.
        element: <AuthLayout />,
        children: [{ path: '/invitations/:token', element: <InvitationPage /> }],
    },
    {
        element: <RequireAuth />,
        children: [
            {
                element: <AppLayout />,
                children: [
                    { index: true, element: <Navigate to="/projects" replace /> },
                    { path: '/projects', element: <ProjectsPage /> },
                    {
                        path: '/projects/:projectId',
                        element: <ProjectDetailPage />,
                        children: [
                            { index: true, element: <Navigate to="tasks" replace /> },
                            { path: 'tasks', element: <ProjectTasksPage /> },
                            { path: 'members', element: <ProjectMembersPage /> },
                            { path: 'invitations', element: <ProjectInvitationsPage /> },
                            { path: 'activity', element: <ProjectActivityPage /> },
                        ],
                    },
                    { path: '/invitations', element: <ReceivedInvitationsPage /> },
                    { path: '/daily-summary', element: <DailySummaryPage /> },
                    { path: '/profile', element: <ProfilePage /> },
                    { path: '/tasks/:taskId', element: <TaskDetailPage /> },
                ],
            },
        ],
    },
    { path: '*', element: <NotFound /> },
])