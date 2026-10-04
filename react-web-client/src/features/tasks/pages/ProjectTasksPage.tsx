import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { EmptyState, ErrorState, LoadingState } from '@/components/feedback/states'
import { Button } from '@/components/ui/Button'
import { SearchIcon } from '@/components/ui/icons'
import { Pagination } from '@/components/ui/Pagination'
import { TextField } from '@/components/ui/TextField'
import { canManageTasks } from '@/features/projects/permissions'
import { useProject } from '@/features/projects/queries'
import { getErrorMessage } from '@/lib/form-errors'
import { useDebouncedValue } from '@/lib/use-debounced-value'
import { TaskFormModal } from '../components/TaskFormModal'
import { TaskRow } from '../components/TaskRow'
import { useProjectTasks } from '../queries'
import { TASK_STATUS_LABELS, TASK_STATUSES } from '../status'
import type { TaskStatus } from '../types'

type StatusFilter = TaskStatus | 'all'

const statusFilters: { value: StatusFilter; label: string }[] = [
    { value: 'all', label: 'All' },
    ...TASK_STATUSES.map((status) => ({ value: status, label: TASK_STATUS_LABELS[status] })),
]

export function ProjectTasksPage() {
    const { projectId } = useParams()
    const id = Number(projectId)
    // Layout induk sudah memuat project, jadi ini langsung terisi dari cache.
    const { data: project } = useProject(id)
    const canManage = canManageTasks(project?.role)

    const [search, setSearch] = useState('')
    const [status, setStatus] = useState<StatusFilter>('all')
    const [page, setPage] = useState(1)
    const [creating, setCreating] = useState(false)
    const debouncedSearch = useDebouncedValue(search.trim(), 300)

    const { data, isPending, isError, error, refetch, isPlaceholderData } = useProjectTasks(id, {
        page,
        title: debouncedSearch || undefined,
        status: status === 'all' ? undefined : status,
    })

    function renderContent() {
        if (isPending) return <LoadingState />
        if (isError) return <ErrorState message={getErrorMessage(error)} onRetry={() => void refetch()} />

        if (data.items.length === 0) {
            if (debouncedSearch !== '' || status !== 'all') {
                return <EmptyState title="No tasks found" description="No tasks match your search or filter." />
            }
            return (
                <EmptyState
                    title="No tasks yet"
                    description={
                        canManage
                            ? 'Create the first task for this project.'
                            : 'Tasks created by owners and editors will appear here.'
                    }
                    action={
                        canManage ? (
                            <Button type="button" fullWidth={false} onClick={() => setCreating(true)}>
                                New task
                            </Button>
                        ) : undefined
                    }
                />
            )
        }

        return (
            <div className={isPlaceholderData ? 'opacity-60 transition-opacity' : 'transition-opacity'}>
                <ul className="space-y-3">
                    {data.items.map((task) => (
                        <TaskRow key={task.id} task={task} />
                    ))}
                </ul>
                <Pagination meta={data.meta} onPageChange={setPage} />
            </div>
        )
    }

    return (
        <div>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex w-full flex-wrap items-center gap-3 sm:w-auto">
                    <div className="w-full sm:w-64">
                        <TextField
                            label="Search tasks"
                            type="search"
                            placeholder="Search tasks"
                            icon={<SearchIcon />}
                            maxLength={255}
                            value={search}
                            onChange={(event) => {
                                setSearch(event.target.value)
                                setPage(1)
                            }}
                        />
                    </div>
                    <div role="group" aria-label="Filter by status" className="flex flex-wrap gap-2">
                        {statusFilters.map((filter) => (
                            <button
                                key={filter.value}
                                type="button"
                                aria-pressed={status === filter.value}
                                onClick={() => {
                                    setStatus(filter.value)
                                    setPage(1)
                                }}
                                className={`rounded-full px-3 py-1 text-sm font-medium transition ${
                                    status === filter.value
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50'
                                }`}
                            >
                                {filter.label}
                            </button>
                        ))}
                    </div>
                </div>

                {canManage && (
                    <Button type="button" fullWidth={false} onClick={() => setCreating(true)}>
                        New task
                    </Button>
                )}
            </div>

            <div className="mt-6">{renderContent()}</div>

            <TaskFormModal open={creating} onClose={() => setCreating(false)} projectId={id} />
        </div>
    )
}