import { Link, Outlet } from 'react-router-dom'
import { useAuth } from '@/features/auth/auth-context'
import { useReceivedInvitations } from '@/features/invitations/queries'

export function AppLayout() {
    const { user, logout } = useAuth()
    // per_page=1: yang dibutuhkan hanya meta.total untuk lencana, bukan isinya.
    const { data: received } = useReceivedInvitations({ per_page: 1 })
    const pendingCount = received?.meta.total ?? 0

    return (
        <div className="min-h-screen bg-gray-50">
            <header className="flex items-center justify-between border-b border-gray-200 bg-white px-6 py-3">
                <Link to="/projects" className="text-lg font-bold text-blue-600">
                    TaskFlow
                </Link>
                <div className="flex items-center gap-4 text-sm">
                    <Link to="/invitations" className="rounded-xl px-3 py-1 font-medium text-gray-700 hover:bg-gray-100">
                        Invitations
                        {pendingCount > 0 && (
                            <span className="ml-2 rounded-full bg-blue-600 px-2 py-0.5 text-xs font-semibold text-white">
                                {pendingCount > 99 ? '99+' : pendingCount}
                                <span className="sr-only"> pending invitations</span>
                            </span>
                        )}
                    </Link>
                    <span className="text-gray-600">{user?.name}</span>
                    <button
                        type="button"
                        onClick={() => void logout()}
                        className="rounded-xl border border-gray-200 px-3 py-1 font-medium text-gray-700 hover:bg-gray-100"
                    >
                        Logout
                    </button>
                </div>
            </header>
            <main className="mx-auto w-full max-w-6xl p-6">
                <Outlet />
            </main>
        </div>
    )
}