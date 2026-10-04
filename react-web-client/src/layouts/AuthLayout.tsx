import { Outlet } from 'react-router-dom'

export function AuthLayout() {
    return (
        <div className="flex min-h-screen">
            <div className="hidden w-1/2 items-center justify-center bg-linear-to-tr from-sky-400 to-blue-600 lg:flex">
                <div className="px-12">
                    <h1 className="text-4xl font-bold text-white">TaskFlow</h1>
                    <p className="mt-2 text-white/90">Manage projects and tasks with your team, all in one place.</p>
                </div>
            </div>
            <div className="flex w-full items-center justify-center bg-white px-6 lg:w-1/2">
                <div className="w-full max-w-sm">
                    <Outlet />
                </div>
            </div>
        </div>
    )
}