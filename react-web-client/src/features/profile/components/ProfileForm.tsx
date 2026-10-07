import { zodResolver } from '@hookform/resolvers/zod'
import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { useToast } from '@/components/feedback/toast-context'
import { Button } from '@/components/ui/Button'
import { SelectField } from '@/components/ui/SelectField'
import { TextField } from '@/components/ui/TextField'
import type { User } from '@/features/auth/types'
import { applyFieldErrors, getErrorMessage } from '@/lib/form-errors'
import { useUpdateProfile } from '../queries'
import { profileSchema, type ProfileFormValues } from '../schemas'
import { getTimezoneOptions } from '../timezones'
import type { UpdateProfilePayload } from '../types'

export function ProfileForm({ user }: { user: User }) {
    const updateProfile = useUpdateProfile()
    const { showToast } = useToast()
    const [formError, setFormError] = useState<string | null>(null)
    const timezones = useMemo(() => getTimezoneOptions(user.timezone), [user.timezone])
    const {
        register,
        handleSubmit,
        setError,
        reset,
        formState: { errors, isSubmitting, isDirty },
    } = useForm<ProfileFormValues>({
        resolver: zodResolver(profileSchema),
        defaultValues: { name: user.name, timezone: user.timezone },
    })

    const onSubmit = handleSubmit(async (values) => {
        setFormError(null)
        // Kirim hanya field yang berubah (minimal satu wajib ada).
        const changes: UpdateProfilePayload = {}
        if (values.name !== user.name) changes.name = values.name
        if (values.timezone !== user.timezone) changes.timezone = values.timezone
        if (Object.keys(changes).length === 0) return

        try {
            const profile = await updateProfile.mutateAsync(changes)
            reset({ name: profile.name, timezone: profile.timezone })
            showToast('Profile updated.')
        } catch (error) {
            // 422 key `timezone` bila bukan identifier IANA yang valid.
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

            <TextField
                label="Name"
                showLabel
                type="text"
                autoComplete="name"
                error={errors.name?.message}
                {...register('name')}
            />

            <div>
                <SelectField label="Timezone" error={errors.timezone?.message} {...register('timezone')}>
                    {timezones.map((zone) => (
                        <option key={zone} value={zone}>
                            {zone.replaceAll('_', ' ')}
                        </option>
                    ))}
                </SelectField>
                <p className="mt-1 ml-2 text-xs text-gray-500">
                    Your daily summary groups activity into days using this timezone.
                </p>
            </div>

            <div className="flex justify-end pt-2">
                <Button type="submit" fullWidth={false} loading={isSubmitting} disabled={!isDirty}>
                    Save changes
                </Button>
            </div>
        </form>
    )
}