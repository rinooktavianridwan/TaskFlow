import { Link, useParams } from 'react-router-dom'
import { EmptyState, ErrorState, LoadingState } from '@/components/feedback/states'
import { Pagination } from '@/components/ui/Pagination'
import { SelectField } from '@/components/ui/SelectField'
import { useMembers } from '@/features/members/queries'
import { canManageProject } from '@/features/projects/permissions'
import { useProject } from '@/features/projects/queries'
import { formatDateTime } from '@/lib/dates'
import { getErrorMessage } from '@/lib/form-errors'
import { parseOption, parsePage, toPageParam, useUrlParams } from '@/lib/use-url-params'
import {
    getActionLabel,
    getActionTone,
    getMetadataString,
    PROJECT_ACTIVITY_ACTIONS,
    type ActivityTone,
} from '../actions'
import { useProjectActivities } from '../queries'
import type { ProjectActivity } from '../types'

const dotClasses: Record<ActivityTone, string> = {
    default: 'bg-blue-400',
    success: 'bg-green-500',
    danger: 'bg-red-400',
}

// Nilai dari URL tidak bisa dipercaya: id harus bilangan bulat >= 1.
function parseActorId(value: string | null): number | null {
    const id = Number(value)
    return Number.isInteger(id) && id >= 1 ? id : null
}

export function ProjectActivityPage() {
    const { projectId } = useParams()
    const id = Number(projectId)
    const { data: project } = useProject(id)

    // Hanya owner yang boleh melihat timeline (backend membalas 403 untuk yang lain).
    // Dicek di wrapper agar query timeline tidak pernah ditembakkan oleh non-owner.
    if (!canManageProject(project?.role)) {
        return <ErrorState title="No access" message="Only project owners can view the activity timeline." />
    }

    return <ActivityContent projectId={id} />
}

function ActivityContent({ projectId }: { projectId: number }) {
    const [params, updateParams] = useUrlParams()
    const action = parseOption(params.get('action'), PROJECT_ACTIVITY_ACTIONS)
    const actorId = parseActorId(params.get('actor'))
    const page = parsePage(params.get('page'))

    // Maksimal 100 member (batas backend); dari cache bila form task sudah memuatnya.
    const members = useMembers(projectId, { per_page: 100 })
    const { data, isPending, isError, error, refetch, isPlaceholderData } = useProjectActivities(projectId, {
        page,
        action: action ?? undefined,
        actor_id: actorId ?? undefined,
    })

    const actorOptions = (members.data?.items ?? []).map((member) => ({ id: member.user_id, name: member.name }))
    // Member yang sudah dikeluarkan tetap bisa menjadi filter lewat URL.
    if (actorId !== null && !actorOptions.some((option) => option.id === actorId)) {
        actorOptions.push({ id: actorId, name: `User #${actorId}` })
    }

    function renderContent() {
        if (isPending) return <LoadingState />
        if (isError) return <ErrorState message={getErrorMessage(error)} onRetry={() => void refetch()} />

        if (data.items.length === 0) {
            return action !== null || actorId !== null ? (
                <EmptyState title="No activity found" description="No activity matches these filters." />
            ) : (
                <EmptyState title="No activity yet" description="Changes made in this project will appear here." />
            )
        }

        return (
            <div className={isPlaceholderData ? 'opacity-60 transition-opacity' : 'transition-opacity'}>
                <ol className="space-y-4 border-l-2 border-gray-100 pl-5">
                    {data.items.map((activity) => (
                        <ActivityRow key={activity.id} activity={activity} />
                    ))}
                </ol>
                <Pagination meta={data.meta} onPageChange={(next) => updateParams({ page: toPageParam(next) })} />
            </div>
        )
    }

    return (
        <div>
            <div className="grid gap-3 sm:max-w-xl sm:grid-cols-2">
                <SelectField
                    label="Type"
                    value={action ?? ''}
                    onChange={(event) => updateParams({ action: event.target.value || null, page: null })}
                >
                    <option value="">All types</option>
                    {PROJECT_ACTIVITY_ACTIONS.map((value) => (
                        <option key={value} value={value}>
                            {getActionLabel(value)}
                        </option>
                    ))}
                </SelectField>
                <SelectField
                    label="Member"
                    value={actorId === null ? '' : String(actorId)}
                    onChange={(event) => updateParams({ actor: event.target.value || null, page: null })}
                >
                    <option value="">All members</option>
                    {actorOptions.map((option) => (
                        <option key={option.id} value={option.id}>
                            {option.name}
                        </option>
                    ))}
                </SelectField>
            </div>

            <div className="mt-6">{renderContent()}</div>
        </div>
    )
}

function ActivityRow({ activity }: { activity: ProjectActivity }) {
    const taskId = activity.task_id
    const taskTitle = getMetadataString(activity.metadata, 'task_title')

    return (
        <li className="relative">
            <span
                aria-hidden="true"
                className={`absolute top-1.5 -left-[26px] h-2.5 w-2.5 rounded-full ${dotClasses[getActionTone(activity.action)]}`}
            />
            <p className="text-sm text-gray-800">{activity.description}</p>
            <p className="text-xs text-gray-400">
                {getActionLabel(activity.action)} · {activity.actor?.name ?? 'Deleted user'} ·{' '}
                {formatDateTime(activity.created_at)}
            </p>
            {/* task_id bisa menunjuk task yang sudah dihapus: tautan tidak dibuat untuk aksi penghapusan,
                dan halaman task sendiri menangani 404 bila task dihapus belakangan. */}
            {taskId !== null && activity.action !== 'task_deleted' && (
                <Link to={`/tasks/${taskId}`} className="mt-0.5 inline-block text-xs text-blue-600 hover:underline">
                    {taskTitle ?? 'View task'}
                </Link>
            )}
        </li>
    )
}