import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useToast } from '@/components/feedback/toast-context'
import { Button } from '@/components/ui/Button'
import { PasswordField } from '@/components/ui/PasswordField'
import { applyFieldErrors, getErrorMessage, getErrorStatus } from '@/lib/form-errors'
import { useUpdatePassword } from '../queries'
import { changePasswordSchema, type ChangePasswordFormValues } from '../schemas'

export function PasswordForm() {
    const updatePassword = useUpdatePassword()
    const { showToast } = useToast()
    const [formError, setFormError] = useState<string | null>(null)
    const {
        register,
        handleSubmit,
        setError,
        reset,
        formState: { errors, isSubmitting },
    } = useForm<ChangePasswordFormValues>({
        resolver: zodResolver(changePasswordSchema),
        defaultValues: { current_password: '', password: '', password_confirmation: '' },
    })

    const onSubmit = handleSubmit(async (values) => {
        setFormError(null)
        try {
            await updatePassword.mutateAsync(values)
            reset()
            showToast('Password updated.')
        } catch (error) {
            // Dibatasi 6 percobaan per menit; 429 hanya berisi { message }.
            if (getErrorStatus(error) === 429) {
                setFormError('Too many attempts. Please try again later.')
                return
            }
            // 422: key `current_password` (salah) atau `password` (konfirmasi, sama dengan lama, terlalu pendek).
            if (!applyFieldErrors(error, setError)) setFormError(getErrorMessage(error))
        }
    })

    return (
        <form onSubmit={onSubmit} noValidate className="space-y-4">
            {formError && (
                <p role="alert" className="rounded-2xl bg-red-50 px-3 py-2 text-sm text-red-600">
                    {formError}
                </p>
            )}

            <PasswordField
                label="Current password"
                placeholder="Current password"
                autoComplete="current-password"
                error={errors.current_password?.message}
                {...register('current_password')}
            />
            <PasswordField
                label="New password"
                placeholder="New password"
                autoComplete="new-password"
                error={errors.password?.message}
                {...register('password')}
            />
            <PasswordField
                label="Confirm new password"
                placeholder="Confirm new password"
                autoComplete="new-password"
                error={errors.password_confirmation?.message}
                {...register('password_confirmation')}
            />

            <div className="flex justify-end pt-2">
                <Button type="submit" fullWidth={false} loading={isSubmitting}>
                    Update password
                </Button>
            </div>
        </form>
    )
}