import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/Button'
import { AtSymbolIcon } from '@/components/ui/icons'
import { TextField } from '@/components/ui/TextField'
import { forgotPassword } from '@/features/auth/api'
import { forgotPasswordSchema, type ForgotPasswordFormValues } from '@/features/auth/schemas'
import { applyFieldErrors, getErrorMessage } from '@/lib/form-errors'

export function ForgotPasswordPage() {
    const [submitted, setSubmitted] = useState(false)
    const [formError, setFormError] = useState<string | null>(null)
    const {
        register,
        handleSubmit,
        setError,
        formState: { errors, isSubmitting },
    } = useForm<ForgotPasswordFormValues>({ resolver: zodResolver(forgotPasswordSchema) })

    const onSubmit = handleSubmit(async (values) => {
        setFormError(null)
        try {
            // Email terdaftar selalu huruf kecil (register menolak huruf besar).
            await forgotPassword({ email: values.email.toLowerCase() })
            setSubmitted(true)
        } catch (error) {
            // 422 hanya untuk format email; 429 (throttle) tampil sebagai banner.
            if (!applyFieldErrors(error, setError)) setFormError(getErrorMessage(error))
        }
    })

    // Pesan netral tidak bergantung pada respons server: backend selalu membalas 200,
    // sehingga halaman ini tidak boleh membocorkan apakah sebuah email terdaftar.
    if (submitted) {
        return (
            <div className="space-y-4">
                <div>
                    <h1 className="mb-1 text-2xl font-bold text-gray-800">Check your email</h1>
                    <p className="text-sm text-gray-600">
                        If the email is registered, a reset link has been sent.
                    </p>
                </div>
                <Link to="/login" className="block text-center text-sm font-semibold text-blue-600 hover:underline">
                    Back to login
                </Link>
            </div>
        )
    }

    return (
        <form onSubmit={onSubmit} noValidate className="space-y-4">
            <div>
                <h1 className="mb-1 text-2xl font-bold text-gray-800">Forgot password?</h1>
                <p className="text-sm text-gray-600">Enter your email and we'll send you a reset link.</p>
            </div>

            {formError && (
                <p role="alert" className="rounded-2xl bg-red-50 px-3 py-2 text-sm text-red-600">
                    {formError}
                </p>
            )}

            <TextField
                label="Email address"
                type="email"
                autoComplete="email"
                placeholder="Email Address"
                icon={<AtSymbolIcon />}
                error={errors.email?.message}
                {...register('email')}
            />

            <Button type="submit" loading={isSubmitting}>
                Send reset link
            </Button>

            <p className="text-center text-sm text-gray-600">
                Remembered it?{' '}
                <Link to="/login" className="font-semibold text-blue-600 hover:underline">
                    Back to login
                </Link>
            </p>
        </form>
    )
}