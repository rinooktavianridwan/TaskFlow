import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Button } from '@/components/ui/Button'
import { AtSymbolIcon } from '@/components/ui/icons'
import { Modal } from '@/components/ui/Modal'
import { SelectField } from '@/components/ui/SelectField'
import { TextField } from '@/components/ui/TextField'
import { PROJECT_ROLE_LABELS, PROJECT_ROLES } from '@/features/projects/roles'
import { applyFieldErrors, getErrorMessage } from '@/lib/form-errors'
import { useCreateInvitation } from '../queries'
import { invitationSchema, type InvitationFormValues } from '../schemas'

type InvitationFormModalProps = {
    open: boolean
    onClose: () => void
    projectId: number
}

export function InvitationFormModal({ open, onClose, projectId }: InvitationFormModalProps) {
    return (
        <Modal open={open} onClose={onClose} title="Invite member">
            <InvitationForm projectId={projectId} onClose={onClose} />
        </Modal>
    )
}

function InvitationForm({ projectId, onClose }: { projectId: number; onClose: () => void }) {
    const createInvitation = useCreateInvitation(projectId)
    const [formError, setFormError] = useState<string | null>(null)
    const {
        register,
        handleSubmit,
        setError,
        formState: { errors, isSubmitting },
    } = useForm<InvitationFormValues>({
        resolver: zodResolver(invitationSchema),
        defaultValues: { email: '', role: 'viewer' },
    })

    const onSubmit = handleSubmit(async (values) => {
        setFormError(null)
        try {
            await createInvitation.mutateAsync(values)
            onClose()
        } catch (error) {
            // 422: email sudah member / masih ada undangan pending untuk email itu.
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
                label="Email address"
                type="email"
                autoComplete="off"
                placeholder="Email Address"
                icon={<AtSymbolIcon />}
                error={errors.email?.message}
                {...register('email')}
            />

            <div>
                <SelectField label="Role" error={errors.role?.message} {...register('role')}>
                    {PROJECT_ROLES.map((role) => (
                        <option key={role} value={role}>
                            {PROJECT_ROLE_LABELS[role]}
                        </option>
                    ))}
                </SelectField>
                <p className="mt-1 ml-2 text-xs text-gray-500">
                    An invitation email will be sent to this address. It expires in 7 days.
                </p>
            </div>

            <div className="flex justify-end gap-3 pt-2">
                <Button type="button" variant="secondary" fullWidth={false} onClick={onClose} disabled={isSubmitting}>
                    Cancel
                </Button>
                <Button type="submit" fullWidth={false} loading={isSubmitting}>
                    Send invitation
                </Button>
            </div>
        </form>
    )
}