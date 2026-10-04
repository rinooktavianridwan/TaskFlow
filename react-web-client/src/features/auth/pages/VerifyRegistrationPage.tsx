import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link, Navigate, useSearchParams } from 'react-router-dom'
import { Button } from '@/components/ui/Button'
import { TextField } from '@/components/ui/TextField'
import { useAuth } from '@/features/auth/auth-context'
import {
    verifyRegistrationSchema,
    type VerifyRegistrationFormValues,
} from '@/features/auth/schemas'
import { applyFieldErrors, getErrorMessage } from '@/lib/form-errors'

export function VerifyRegistrationPage() {
    const [searchParams] = useSearchParams()
    const email = searchParams.get('email')

    // Dibuka tanpa email (mis. langsung mengetik URL): tidak ada yang bisa diverifikasi.
    if (!email) return <Navigate to="/register" replace />

    return <VerifyRegistrationForm email={email} />
}

function VerifyRegistrationForm({ email }: { email: string }) {
    const { verifyRegistration } = useAuth()
    const [formError, setFormError] = useState<string | null>(null)
    const {
        register,
        handleSubmit,
        setError,
        formState: { errors, isSubmitting },
    } = useForm<VerifyRegistrationFormValues>({ resolver: zodResolver(verifyRegistrationSchema) })

    const onSubmit = handleSubmit(async (values) => {
        setFormError(null)
        try {
            // Sukses = otomatis login; GuestOnly yang mengarahkan ke dalam aplikasi.
            await verifyRegistration({ email, otp_code: values.otp_code })
        } catch (error) {
            if (!applyFieldErrors(error, setError)) setFormError(getErrorMessage(error))
        }
    })

    return (
        <form onSubmit={onSubmit} noValidate className="space-y-4">
            <div>
                <h1 className="mb-1 text-2xl font-bold text-gray-800">Verify your email</h1>
                <p className="text-sm text-gray-600">
                    We sent a 6-digit code to <span className="font-semibold">{email}</span>. It expires in 10 minutes.
                </p>
            </div>

            {formError && (
                <p role="alert" className="rounded-2xl bg-red-50 px-3 py-2 text-sm text-red-600">
                    {formError}
                </p>
            )}

            <TextField
                label="Verification code"
                type="text"
                inputMode="numeric"
                autoComplete="one-time-code"
                maxLength={6}
                placeholder="000000"
                className="text-center text-lg tracking-[0.5em]"
                error={errors.otp_code?.message}
                {...register('otp_code')}
            />

            <Button type="submit" loading={isSubmitting}>
                Verify
            </Button>

            <p className="text-center text-sm text-gray-600">
                Wrong email or code expired?{' '}
                <Link to="/register" className="font-semibold text-blue-600 hover:underline">
                    Start over
                </Link>
            </p>
        </form>
    )
}