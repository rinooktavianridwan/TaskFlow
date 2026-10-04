import { Outlet } from 'react-router-dom'
import { useAuth } from '@/features/auth/auth-context'

export function AppLayout() {
    const { user, logout } = useAuth()

    return (
        <div className="min-h-screen bg-gray-50">
            <header className="flex items-center justify-between border-b border-gray-200 bg-white px-6 py-3">
                <span className="text-lg font-bold text-blue-600">TaskFlow</span>
                <div className="flex items-center gap-4 text-sm">
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
            <main className="p-6">
                <Outlet />
            </main>
        </div>
    )
}