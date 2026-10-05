import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { withRedirect } from '@/lib/redirect'
import { useAuth } from './auth-context'
import { useRedirectParam } from './use-redirect-param'

function FullPageLoader() {
    return <div className="grid min-h-screen place-items-center text-gray-500">Loading…</div>
}

export function RequireAuth() {
    const { user, isLoading } = useAuth()
    const location = useLocation()

    if (isLoading) return <FullPageLoader />
    if (!user) {
        // Simpan tujuan di URL (bukan location.state) supaya tahan refresh dan bisa dibawa antar halaman auth.
        const destination = `${location.pathname}${location.search}${location.hash}`
        return <Navigate to={withRedirect('/login', destination)} replace />
    }
    return <Outlet />
}

export function GuestOnly() {
    const { user, isLoading } = useAuth()
    const redirect = useRedirectParam()

    if (isLoading) return <FullPageLoader />
    if (user) return <Navigate to={redirect ?? '/'} replace />
    return <Outlet />
}