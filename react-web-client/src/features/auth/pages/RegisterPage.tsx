import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link, useNavigate } from 'react-router-dom'
import { Button } from '@/components/ui/Button'
import { AtSymbolIcon, LockClosedIcon, UserIcon } from '@/components/ui/icons'
import { PasswordField } from '@/components/ui/PasswordField'
import { TextField } from '@/components/ui/TextField'
import { register as registerRequest } from '@/features/auth/api'
import { registerSchema, type RegisterFormValues } from '@/features/auth/schemas'
import { useRedirectParam } from '@/features/auth/use-redirect-param'
import { applyFieldErrors, getErrorMessage } from '@/lib/form-errors'
import { withRedirect } from '@/lib/redirect'

export function RegisterPage() {
    const navigate = useNavigate()
    const redirect = useRedirectParam()
    const [formError, setFormError] = useState<string | null>(null)
    const {
        register,
        handleSubmit,
        setError,
        formState: { errors, isSubmitting },
    } = useForm<RegisterFormValues>({ resolver: zodResolver(registerSchema) })

    const onSubmit = handleSubmit(async (values) => {
        setFormError(null)
        try {
            // Backend menolak email berhuruf besar (bukan menormalkannya), jadi kita normalkan di sini.
            const { email } = await registerRequest({ ...values, email: values.email.toLowerCase() })
            navigate(withRedirect('/register/verify', redirect, { email }))
        } catch (error) {
            if (!applyFieldErrors(error, setError)) setFormError(getErrorMessage(error))
        }
    })

    return (
        <form onSubmit={onSubmit} noValidate className="space-y-4">
            <div>
                <h1 className="mb-1 text-2xl font-bold text-gray-800">Create account</h1>
                <p className="text-sm text-gray-600">Sign up to start managing your projects</p>
            </div>

            {formError && (
                <p role="alert" className="rounded-2xl bg-red-50 px-3 py-2 text-sm text-red-600">
                    {formError}
                </p>
            )}

            <TextField
                label="Full name"
                type="text"
                autoComplete="name"
                placeholder="Full Name"
                icon={<UserIcon />}
                error={errors.name?.message}
                {...register('name')}
            />
            <TextField
                label="Email address"
                type="email"
                autoComplete="email"
                placeholder="Email Address"
                icon={<AtSymbolIcon />}
                error={errors.email?.message}
                {...register('email')}
            />
            <PasswordField
                label="Password"
                autoComplete="new-password"
                placeholder="Password"
                icon={<LockClosedIcon />}
                error={errors.password?.message}
                {...register('password')}
            />
            <PasswordField
                label="Confirm password"
                autoComplete="new-password"
                placeholder="Confirm Password"
                icon={<LockClosedIcon />}
                error={errors.password_confirmation?.message}
                {...register('password_confirmation')}
            />

            <Button type="submit" loading={isSubmitting}>
                Sign up
            </Button>

            <p className="text-center text-sm text-gray-600">
                Already have an account?{' '}
                <Link to={withRedirect('/login', redirect)} className="font-semibold text-blue-600 hover:underline">
                    Login
                </Link>
            </p>
        </form>
    )
}