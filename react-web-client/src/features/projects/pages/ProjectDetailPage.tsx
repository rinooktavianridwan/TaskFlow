import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { ErrorState, LoadingState } from '@/components/feedback/states'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { formatDate } from '@/lib/dates'
import { getErrorMessage, getErrorStatus } from '@/lib/form-errors'
import { ProjectFormModal } from '../components/ProjectFormModal'
import { RoleBadge } from '../components/RoleBadge'
import { canManageProject } from '../permissions'
import { useDeleteProject, useProject } from '../queries'

export function ProjectDetailPage() {
    const { projectId } = useParams()
    const id = Number(projectId)
    const navigate = useNavigate()
    const { data: project, isPending, isError, error, refetch } = useProject(id)
    const deleteProject = useDeleteProject()
    const [editing, setEditing] = useState(false)
    const [deleting, setDeleting] = useState(false)
    const [deleteError, setDeleteError] = useState<string | null>(null)

    // Cek id tidak valid harus lebih dulu: query yang dinonaktifkan berstatus "pending" selamanya.
    if (!Number.isInteger(id) || id < 1) {
        return <ErrorState title="Project not found" message="The link is incorrect." />
    }
    if (isPending) return <LoadingState />
    if (isError) {
        const status = getErrorStatus(error)
        if (status === 404) {
            return <ErrorState title="Project not found" message="It may have been deleted, or the link is incorrect." />
        }
        if (status === 403) {
            return <ErrorState title="No access" message="You are not a member of this project." />
        }
        return <ErrorState message={getErrorMessage(error)} onRetry={() => void refetch()} />
    }

    async function handleDelete() {
        setDeleteError(null)
        try {
            await deleteProject.mutateAsync(id)
            navigate('/projects', { replace: true })
        } catch (error) {
            setDeleteError(getErrorMessage(error))
        }
    }

    function closeDeleteDialog() {
        setDeleting(false)
        setDeleteError(null)
    }

    return (
        <div>
            <Link to="/projects" className="text-sm text-gray-500 hover:text-blue-600">
                ← Projects
            </Link>

            <div className="mt-3 flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="text-2xl font-bold break-words text-gray-800">{project.name}</h1>
                        <RoleBadge role={project.role} />
                    </div>
                    <p className="mt-2 max-w-2xl text-sm whitespace-pre-line text-gray-600">
                        {project.description ?? 'No description'}
                    </p>
                    <p className="mt-2 text-xs text-gray-400">
                        Created {formatDate(project.created_at)} · Updated {formatDate(project.updated_at)}
                    </p>
                </div>

                {canManageProject(project.role) && (
                    <div className="flex gap-2">
                        <Button type="button" variant="secondary" fullWidth={false} onClick={() => setEditing(true)}>
                            Edit
                        </Button>
                        <Button type="button" variant="danger" fullWidth={false} onClick={() => setDeleting(true)}>
                            Delete
                        </Button>
                    </div>
                )}
            </div>

            <ProjectFormModal open={editing} onClose={() => setEditing(false)} project={project} />

            <ConfirmDialog
                open={deleting}
                title="Delete project"
                message={`"${project.name}" and all of its tasks will be permanently deleted. This cannot be undone.`}
                confirmLabel="Delete project"
                loading={deleteProject.isPending}
                error={deleteError}
                onConfirm={() => void handleDelete()}
                onCancel={closeDeleteDialog}
            />
        </div>
    )
}