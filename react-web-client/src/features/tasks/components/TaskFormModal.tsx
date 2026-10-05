import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { LoadingState } from '@/components/feedback/states'
import { Button } from '@/components/ui/Button'
import { Modal } from '@/components/ui/Modal'
import { SelectField } from '@/components/ui/SelectField'
import { TextAreaField } from '@/components/ui/TextAreaField'
import { TextField } from '@/components/ui/TextField'
import type { Member } from '@/features/members/types'
import { useMembers } from '@/features/members/queries'
import { toDateTimeLocalValue } from '@/lib/dates'
import { applyFieldErrors, getErrorMessage } from '@/lib/form-errors'
import { diffTask } from '../diff'
import { useCreateTask, useUpdateTask } from '../queries'
import { taskSchema, type TaskFormValues } from '../schemas'
import type { Task, TaskPayload } from '../types'

type TaskFormModalProps = {
    open: boolean
    onClose: () => void
    projectId: number
    task?: Task
}

export function TaskFormModal({ open, onClose, projectId, task }: TaskFormModalProps) {
    return (
        <Modal open={open} onClose={onClose} title={task ? 'Edit task' : 'New task'}>
            <TaskForm projectId={projectId} task={task} onClose={onClose} />
        </Modal>
    )
}

function TaskForm({ projectId, task, onClose }: { projectId: number; task?: Task; onClose: () => void }) {
    // Maksimal 100 member sesuai batas backend. Dari cache jika sudah pernah dimuat.
    const members = useMembers(projectId, { per_page: 100 })

    if (members.isPending) return <LoadingState />

    return (
        <TaskFormFields
            projectId={projectId}
            task={task}
            members={members.data?.items ?? []}
            membersFailed={members.isError}
            onClose={onClose}
        />
    )
}

type AssigneeOption = { id: number; name: string }

type TaskFormFieldsProps = {
    projectId: number
    task?: Task
    members: Member[]
    membersFailed: boolean
    onClose: () => void
}

function TaskFormFields({ projectId, task, members, membersFailed, onClose }: TaskFormFieldsProps) {
    const createTask = useCreateTask(projectId)
    const updateTask = useUpdateTask()
    const [formError, setFormError] = useState<string | null>(null)
    const {
        register,
        handleSubmit,
        setError,
        formState: { errors, isSubmitting },
    } = useForm<TaskFormValues>({
        resolver: zodResolver(taskSchema),
        defaultValues: {
            title: task?.title ?? '',
            description: task?.description ?? '',
            assigned_to: task?.assigned_to ? String(task.assigned_to) : '',
            due_date: toDateTimeLocalValue(task?.due_date ?? null),
        },
    })

    const assigneeOptions: AssigneeOption[] = members.map((member) => ({ id: member.user_id, name: member.name }))
    const currentAssignee = task?.assignee
    if (currentAssignee && !assigneeOptions.some((option) => option.id === currentAssignee.id)) {
        assigneeOptions.push({ id: currentAssignee.id, name: currentAssignee.name })
    }

    const onSubmit = handleSubmit(async (values) => {
        setFormError(null)
        const payload: TaskPayload = {
            title: values.title,
            description: values.description || null,
            assigned_to: values.assigned_to ? Number(values.assigned_to) : null,
            // datetime-local berisi waktu lokal tanpa zona; ubah ke UTC sebelum dikirim.
            due_date: values.due_date ? new Date(values.due_date).toISOString() : null,
        }

        try {
            if (task) {
                // Kirim hanya field yang berubah; tanpa perubahan, tidak perlu request sama sekali.
                const changes = diffTask(task, payload)
                if (Object.keys(changes).length > 0) {
                    await updateTask.mutateAsync({ id: task.id, payload: changes })
                }
            } else {
                await createTask.mutateAsync(payload)
            }
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
                    <option value="">Unassigned</option>
                    {assigneeOptions.map((option) => (
                        <option key={option.id} value={option.id}>
                            {option.name}
                        </option>
                    ))}
                </SelectField>
                {membersFailed && (
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
                    {task ? 'Save changes' : 'Create task'}
                </Button>
            </div>
        </form>
    )
}
