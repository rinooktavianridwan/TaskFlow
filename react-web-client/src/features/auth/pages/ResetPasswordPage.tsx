import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { useToast } from '@/components/feedback/toast-context'
import { Button } from '@/components/ui/Button'
import { LockClosedIcon } from '@/components/ui/icons'
import { PasswordField } from '@/components/ui/PasswordField'
import { resetPassword } from '@/features/auth/api'
import { resetPasswordSchema, type ResetPasswordFormValues } from '@/features/auth/schemas'
import { applyFieldErrors, getErrorMessage, getFieldError } from '@/lib/form-errors'

export function ResetPasswordPage() {
    const { token } = useParams()
    const [searchParams] = useSearchParams()
    const email = searchParams.get('email')

    // Tautan tanpa token atau email tidak bisa dipakai: arahkan untuk meminta tautan baru.
    if (!token || !email) {
        return (
            <div className="space-y-4">
                <div>
                    <h1 className="mb-1 text-2xl font-bold text-gray-800">Invalid reset link</h1>
                    <p className="text-sm text-gray-600">This link is incomplete. Request a new one to continue.</p>
                </div>
                <Link
                    to="/forgot-password"
                    className="block w-full rounded-2xl bg-blue-600 px-4 py-2 text-center font-semibold text-white transition hover:bg-blue-700"
                >
                    Request a new link
                </Link>
            </div>
        )
    }

    return <ResetPasswordForm token={token} email={email} />
}

function ResetPasswordForm({ token, email }: { token: string; email: string }) {
    const navigate = useNavigate()
    const { showToast } = useToast()
    const [formError, setFormError] = useState<string | null>(null)
    const [tokenInvalid, setTokenInvalid] = useState(false)
    const {
        register,
        handleSubmit,
        setError,
        formState: { errors, isSubmitting },
    } = useForm<ResetPasswordFormValues>({ resolver: zodResolver(resetPasswordSchema) })

    const onSubmit = handleSubmit(async (values) => {
        setFormError(null)
        setTokenInvalid(false)
        try {
            await resetPassword({ token, email, ...values })
            showToast('Your password has been reset. You can now log in.')
            navigate('/login', { replace: true })
        } catch (error) {
            // 422 key `email` = token salah, kedaluwarsa, atau sudah dipakai. Itu bukan input di form ini,
            // jadi tampil sebagai banner (dengan jalan keluar: minta tautan baru).
            const tokenError = getFieldError(error, 'email')
            if (tokenError) {
                setTokenInvalid(true)
                setFormError(tokenError)
            } else if (!applyFieldErrors(error, setError)) {
                setFormError(getErrorMessage(error))
            }
        }
    })

    return (
        <form onSubmit={onSubmit} noValidate className="space-y-4">
            <div>
                <h1 className="mb-1 text-2xl font-bold text-gray-800">Reset password</h1>
                <p className="text-sm text-gray-600">
                    Choose a new password for <span className="font-semibold">{email}</span>.
                </p>
            </div>

            {formError && (
                <p role="alert" className="rounded-2xl bg-red-50 px-3 py-2 text-sm text-red-600">
                    {formError}
                </p>
            )}

            <PasswordField
                label="New password"
                autoComplete="new-password"
                placeholder="New Password"
                icon={<LockClosedIcon />}
                error={errors.password?.message}
                {...register('password')}
            />
            <PasswordField
                label="Confirm new password"
                autoComplete="new-password"
                placeholder="Confirm New Password"
                icon={<LockClosedIcon />}
                error={errors.password_confirmation?.message}
                {...register('password_confirmation')}
            />

            <Button type="submit" loading={isSubmitting}>
                Reset password
            </Button>

            <p className="text-center text-sm text-gray-600">
                {tokenInvalid ? (
                    <Link to="/forgot-password" className="font-semibold text-blue-600 hover:underline">
                        Request a new link
                    </Link>
                ) : (
                    <Link to="/login" className="font-semibold text-blue-600 hover:underline">
                        Back to login
                    </Link>
                )}
            </p>
        </form>
    )
}