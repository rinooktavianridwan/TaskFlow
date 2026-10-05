import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/Button'
import { AtSymbolIcon, LockClosedIcon } from '@/components/ui/icons'
import { PasswordField } from '@/components/ui/PasswordField'
import { TextField } from '@/components/ui/TextField'
import { useAuth } from '@/features/auth/auth-context'
import { loginSchema, type LoginFormValues } from '@/features/auth/schemas'
import { useRedirectParam } from '@/features/auth/use-redirect-param'
import { applyFieldErrors, getErrorMessage } from '@/lib/form-errors'
import { withRedirect } from '@/lib/redirect'

export function LoginPage() {
    const { login } = useAuth()
    const redirect = useRedirectParam()
    const [formError, setFormError] = useState<string | null>(null)
    const {
        register,
        handleSubmit,
        setError,
        formState: { errors, isSubmitting },
    } = useForm<LoginFormValues>({ resolver: zodResolver(loginSchema) })

    const onSubmit = handleSubmit(async (values) => {
        setFormError(null)
        try {
            await login(values)
        } catch (error) {
            // 422 -> tampil di bawah field; selain itu (mis. 429) -> banner umum.
            if (!applyFieldErrors(error, setError)) setFormError(getErrorMessage(error))
        }
    })

    return (
        <form onSubmit={onSubmit} noValidate className="space-y-4">
            <div>
                <h1 className="mb-1 text-2xl font-bold text-gray-800">Welcome</h1>
                <p className="text-sm text-gray-600">Sign in to your account</p>
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
            <div>
                <PasswordField
                    label="Password"
                    autoComplete="current-password"
                    placeholder="Password"
                    icon={<LockClosedIcon />}
                    error={errors.password?.message}
                    {...register('password')}
                />
                <p className="mt-1 text-right text-sm">
                    {/* ?redirect= tidak dibawa: tautan reset di email tidak bisa membawanya kembali ke sini. */}
                    <Link to="/forgot-password" className="font-semibold text-blue-600 hover:underline">
                        Forgot password?
                    </Link>
                </p>
            </div>

            <Button type="submit" loading={isSubmitting}>
                Login
            </Button>

            <p className="text-center text-sm text-gray-600">
                Don't have an account?{' '}
                <Link to={withRedirect('/register', redirect)} className="font-semibold text-blue-600 hover:underline">
                    Sign up
                </Link>
            </p>
        </form>
    )
}