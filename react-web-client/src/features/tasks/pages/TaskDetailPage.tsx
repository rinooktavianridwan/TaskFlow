import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { ErrorState, LoadingState } from '@/components/feedback/states'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { useAuth } from '@/features/auth/auth-context'
import { canChangeTaskStatus, canManageTasks } from '@/features/projects/permissions'
import { useProject } from '@/features/projects/queries'
import { formatDateTime } from '@/lib/dates'
import { getErrorMessage, getErrorStatus } from '@/lib/form-errors'
import { DueDate } from '../components/DueDate'
import { StatusSelect } from '../components/StatusSelect'
import { TaskActivityList } from '../components/TaskActivityList'
import { TaskFormModal } from '../components/TaskFormModal'
import { useDeleteTask, useTask } from '../queries'
import type { Task } from '../types'

export function TaskDetailPage() {
    const { taskId } = useParams()
    const id = Number(taskId)
    const navigate = useNavigate()
    const { user } = useAuth()
    const { data: task, isPending, isError, error, refetch } = useTask(id)
    // Respons task tidak memuat role; ambil dari project (biasanya sudah ada di cache).
    // Id 0 menonaktifkan query sampai task selesai dimuat.
    const { data: project } = useProject(task?.project_id ?? 0)
    const deleteTask = useDeleteTask()
    const [editing, setEditing] = useState(false)
    const [deleting, setDeleting] = useState(false)
    const [deleteError, setDeleteError] = useState<string | null>(null)

    // Cek id tidak valid harus lebih dulu: query yang dinonaktifkan berstatus "pending" selamanya.
    if (!Number.isInteger(id) || id < 1) {
        return <ErrorState title="Task not found" message="The link is incorrect." />
    }
    if (isPending) return <LoadingState />
    if (isError) {
        const status = getErrorStatus(error)
        if (status === 404) {
            return <ErrorState title="Task not found" message="It may have been deleted, or the link is incorrect." />
        }
        if (status === 403) {
            return <ErrorState title="No access" message="You are not a member of this project." />
        }
        return <ErrorState message={getErrorMessage(error)} onRetry={() => void refetch()} />
    }

    const canManage = canManageTasks(project?.role)
    const canChangeStatus = canChangeTaskStatus(project?.role, task.assigned_to, user?.id)

    async function handleDelete(target: Task) {
        setDeleteError(null)
        try {
            await deleteTask.mutateAsync(target)
            navigate(`/projects/${target.project_id}/tasks`, { replace: true })
        } catch (error) {
            setDeleteError(getErrorMessage(error))
        }
    }

    function closeDeleteDialog() {
        setDeleting(false)
        setDeleteError(null)
    }

    return (
        <div className="mx-auto max-w-3xl">
            <Link
                to={`/projects/${task.project_id}/tasks`}
                className="text-sm text-gray-500 hover:text-blue-600"
            >
                ← {project?.name ?? 'Back to project'}
            </Link>

            <div className="mt-3 rounded-2xl border border-gray-200 bg-white p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <h1 className="min-w-0 text-2xl font-bold break-words text-gray-800">{task.title}</h1>
                    <StatusSelect task={task} canChange={canChangeStatus} />
                </div>

                <p className="mt-3 text-sm whitespace-pre-line text-gray-600">
                    {task.description ?? 'No description'}
                </p>

                <dl className="mt-6 grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt className="text-xs font-medium text-gray-400 uppercase">Assignee</dt>
                        <dd className="mt-1 text-gray-800">
                            {task.assignee ? `${task.assignee.name} (${task.assignee.email})` : 'Unassigned'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-xs font-medium text-gray-400 uppercase">Due date</dt>
                        <dd className="mt-1 text-gray-800">
                            {task.due_date ? <DueDate task={task} prefix={false} /> : 'No due date'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-xs font-medium text-gray-400 uppercase">Created</dt>
                        <dd className="mt-1 text-gray-800">{formatDateTime(task.created_at)}</dd>
                    </div>
                    <div>
                        <dt className="text-xs font-medium text-gray-400 uppercase">Updated</dt>
                        <dd className="mt-1 text-gray-800">{formatDateTime(task.updated_at)}</dd>
                    </div>
                </dl>

                {canManage && (
                    <div className="mt-6 flex gap-2 border-t border-gray-100 pt-4">
                        <Button type="button" variant="secondary" fullWidth={false} onClick={() => setEditing(true)}>
                            Edit
                        </Button>
                        <Button type="button" variant="danger" fullWidth={false} onClick={() => setDeleting(true)}>
                            Delete
                        </Button>
                    </div>
                )}
            </div>

            <section className="mt-6 rounded-2xl border border-gray-200 bg-white p-6">
                <h2 className="mb-4 text-lg font-bold text-gray-800">Activity</h2>
                <TaskActivityList taskId={task.id} />
            </section>

            <TaskFormModal
                open={editing}
                onClose={() => setEditing(false)}
                projectId={task.project_id}
                task={task}
            />

            <ConfirmDialog
                open={deleting}
                title="Delete task"
                message={`"${task.title}" will be permanently deleted. This cannot be undone.`}
                confirmLabel="Delete task"
                loading={deleteTask.isPending}
                error={deleteError}
                onConfirm={() => void handleDelete(task)}
                onCancel={closeDeleteDialog}
            />
        </div>
    )
}