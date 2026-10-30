import { Link, Outlet } from 'react-router-dom'
import { Avatar } from '@/components/ui/Avatar'
import { useAuth } from '@/features/auth/auth-context'
import { useReceivedInvitations } from '@/features/invitations/queries'
import { useTimezoneSync } from '@/features/profile/use-timezone-sync'

export function AppLayout() {
    const { user, logout } = useAuth()
    // per_page=1: yang dibutuhkan hanya meta.total untuk lencana, bukan isinya.
    const { data: received } = useReceivedInvitations({ per_page: 1 })
    const pendingCount = received?.meta.total ?? 0
    // Sekali saja: terapkan zona waktu browser bila akun masih berzona UTC default.
    useTimezoneSync()

    return (
        <div className="min-h-screen bg-gray-50">
            <header className="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-gray-200 bg-white px-4 py-3 sm:flex-nowrap sm:justify-between sm:px-6">
                <Link to="/projects" className="shrink-0 text-lg font-bold text-blue-600">
                    TaskFlow
                </Link>
                <div className="flex basis-full flex-wrap items-center justify-between gap-1 text-sm sm:basis-auto sm:justify-end sm:gap-4">
                    <Link
                        to="/daily-summary"
                        className="rounded-xl px-2 py-1 font-medium text-gray-700 hover:bg-gray-100 sm:px-3"
                    >
                        Daily summary
                    </Link>
                    <Link to="/invitations" className="rounded-xl px-2 py-1 font-medium text-gray-700 hover:bg-gray-100 sm:px-3">
                        Invitations
                        {pendingCount > 0 && (
                            <span className="ml-2 rounded-full bg-blue-600 px-2 py-0.5 text-xs font-semibold text-white">
                                {pendingCount > 99 ? '99+' : pendingCount}
                                <span className="sr-only"> pending invitations</span>
                            </span>
                        )}
                    </Link>
                    {user && (
                        <Link to="/profile" className="flex items-center gap-2 rounded-xl px-2 py-1 hover:bg-gray-100">
                            <Avatar name={user.name} />
                            <span className="hidden text-gray-600 sm:inline">{user.name}</span>
                            <span className="sr-only">Profile</span>
                        </Link>
                    )}
                    <button
                        type="button"
                        onClick={() => void logout()}
                        className="rounded-xl border border-gray-200 px-3 py-1 font-medium text-gray-700 hover:bg-gray-100"
                    >
                        Logout
                    </button>
                </div>
            </header>
            <main className="mx-auto w-full max-w-6xl p-4 sm:p-6">
                <Outlet />
            </main>
        </div>
    )
}