import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Button } from '@/components/ui/Button'
import { Modal } from '@/components/ui/Modal'
import { SelectField } from '@/components/ui/SelectField'
import { TextAreaField } from '@/components/ui/TextAreaField'
import { TextField } from '@/components/ui/TextField'
import { useMembers } from '@/features/members/queries'
import { applyFieldErrors, getErrorMessage } from '@/lib/form-errors'
import { useCreateTask } from '../queries'
import { taskSchema, type TaskFormValues } from '../schemas'

type TaskFormModalProps = {
    open: boolean
    onClose: () => void
    projectId: number
}

export function TaskFormModal({ open, onClose, projectId }: TaskFormModalProps) {
    return (
        <Modal open={open} onClose={onClose} title="New task">
            <TaskForm projectId={projectId} onClose={onClose} />
        </Modal>
    )
}

function TaskForm({ projectId, onClose }: { projectId: number; onClose: () => void }) {
    const createTask = useCreateTask(projectId)
    // Diambil saat modal dibuka (form baru dirender saat open), maksimal 100 member sesuai batas backend.
    const members = useMembers(projectId, { per_page: 100 })
    const [formError, setFormError] = useState<string | null>(null)
    const {
        register,
        handleSubmit,
        setError,
        formState: { errors, isSubmitting },
    } = useForm<TaskFormValues>({
        resolver: zodResolver(taskSchema),
        defaultValues: { title: '', description: '', assigned_to: '', due_date: '' },
    })

    const onSubmit = handleSubmit(async (values) => {
        setFormError(null)
        const payload = {
            title: values.title,
            description: values.description || null,
            assigned_to: values.assigned_to ? Number(values.assigned_to) : null,
            // datetime-local berisi waktu lokal tanpa zona; ubah ke UTC sebelum dikirim.
            due_date: values.due_date ? new Date(values.due_date).toISOString() : null,
        }

        try {
            await createTask.mutateAsync(payload)
            onClose()
        } catch (error) {
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

            <TextField label="Title" type="text" placeholder="Task title" error={errors.title?.message} {...register('title')} />
            <TextAreaField
                label="Description"
                placeholder="Description (optional)"
                error={errors.description?.message}
                {...register('description')}
            />

            <div>
                <SelectField label="Assignee" error={errors.assigned_to?.message} {...register('assigned_to')}>
                    <option value="">{members.isPending ? 'Loading members…' : 'Unassigned'}</option>
                    {members.data?.items.map((member) => (
                        <option key={member.user_id} value={member.user_id}>
                            {member.name}
                        </option>
                    ))}
                </SelectField>
                {members.isError && (
                    <p className="mt-1 ml-2 text-xs text-red-500">Could not load members. You can assign the task later.</p>
                )}
            </div>

            <div>
                <TextField
                    label="Due date"
                    showLabel
                    type="datetime-local"
                    error={errors.due_date?.message}
                    {...register('due_date')}
                />
                <p className="mt-1 ml-2 text-xs text-gray-500">
                    The assignee gets a reminder email 24 hours before the due date.
                </p>
            </div>

            <div className="flex justify-end gap-3 pt-2">
                <Button type="button" variant="secondary" fullWidth={false} onClick={onClose} disabled={isSubmitting}>
                    Cancel
                </Button>
                <Button type="submit" fullWidth={false} loading={isSubmitting}>
                    Create task
                </Button>
            </div>
        </form>
    )
}