import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from './auth-context'

type LocationState = { from?: { pathname: string; search?: string } } | null

function FullPageLoader() {
    return <div className="grid min-h-screen place-items-center text-gray-500">Loading…</div>
}

export function RequireAuth() {
    const { user, isLoading } = useAuth()
    const location = useLocation()

    if (isLoading) return <FullPageLoader />
    if (!user) return <Navigate to="/login" replace state={{ from: location }} />
    return <Outlet />
}

export function GuestOnly() {
    const { user, isLoading } = useAuth()
    const location = useLocation()
    const state: LocationState = location.state

    if (isLoading) return <FullPageLoader />
    if (user) {
        const target = state?.from ? `${state.from.pathname}${state.from.search ?? ''}` : '/'
        return <Navigate to={target} replace />
    }
    return <Outlet />
}