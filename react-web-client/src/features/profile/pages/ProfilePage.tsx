import { Avatar } from '@/components/ui/Avatar'
import { useAuth } from '@/features/auth/auth-context'
import { PasswordForm } from '../components/PasswordForm'
import { ProfileForm } from '../components/ProfileForm'

export function ProfilePage() {
    const { user } = useAuth()
    // RequireAuth sudah menjamin user ada; cek ini hanya untuk menyempitkan tipe.
    if (!user) return null

    return (
        <div className="mx-auto max-w-2xl space-y-6">
            <h1 className="text-2xl font-bold text-gray-800">Profile</h1>

            <section className="rounded-2xl border border-gray-200 bg-white p-6">
                <div className="mb-6 flex items-center gap-4">
                    <Avatar name={user.name} size="lg" />
                    <div className="min-w-0">
                        <p className="font-semibold break-words text-gray-800">{user.name}</p>
                        <p className="text-sm break-all text-gray-500">{user.email}</p>
                        <p className="mt-1 text-xs text-gray-400">Your email address can't be changed.</p>
                    </div>
                </div>
                <ProfileForm user={user} />
            </section>

            <section className="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 className="mb-4 text-lg font-bold text-gray-800">Change password</h2>
                <PasswordForm />
            </section>
        </div>
    )
}