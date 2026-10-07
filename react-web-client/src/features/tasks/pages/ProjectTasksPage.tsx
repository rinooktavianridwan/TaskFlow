import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { EmptyState, ErrorState, LoadingState } from '@/components/feedback/states'
import { Button } from '@/components/ui/Button'
import { Pagination } from '@/components/ui/Pagination'
import { SearchField } from '@/components/ui/SearchField'
import { canManageTasks } from '@/features/projects/permissions'
import { useProject } from '@/features/projects/queries'
import { getErrorMessage } from '@/lib/form-errors'
import { parseOption, parsePage, toPageParam, useUrlParams } from '@/lib/use-url-params'
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

const scopeOptions = [
    { mine: false, label: 'All tasks' },
    { mine: true, label: 'My tasks' },
]

export function ProjectTasksPage() {
    const { projectId } = useParams()
    const id = Number(projectId)
    // Layout induk sudah memuat project, langsung terisi dari cache.
    const { data: project } = useProject(id)
    const canManage = canManageTasks(project?.role)

    const [params, updateParams] = useUrlParams()
    const [creating, setCreating] = useState(false)
    // Pencarian, filter, cakupan, dan halaman disimpan di URL. Nilai tak dikenal jadi default.
    const search = (params.get('q') ?? '').slice(0, 255)
    const status: StatusFilter = parseOption(params.get('status'), TASK_STATUSES) ?? 'all'
    const mine = params.get('mine') === '1'
    const page = parsePage(params.get('page'))
    const hasFilter = search !== '' || status !== 'all' || mine

    const { data, isPending, isError, error, refetch, isPlaceholderData } = useProjectTasks(id, {
        page,
        title: search || undefined,
        status: status === 'all' ? undefined : status,
        mine: mine ? 1 : undefined,
    })

    function renderContent() {
        if (isPending) return <LoadingState />
        if (isError) return <ErrorState message={getErrorMessage(error)} onRetry={() => void refetch()} />

        if (data.items.length === 0) {
            if (hasFilter) {
                const onlyMine = mine && search === '' && status === 'all'
                return (
                    <EmptyState
                        title="No tasks found"
                        description={
                            onlyMine
                                ? 'No tasks in this project are assigned to you.'
                                : 'No tasks match your search or filter.'
                        }
                    />
                )
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
                <Pagination meta={data.meta} onPageChange={(next) => updateParams({ page: toPageParam(next) })} />
            </div>
        )
    }

    return (
        <div>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex w-full flex-wrap items-center gap-3 sm:w-auto">
                    <div className="w-full sm:w-64">
                        <SearchField
                            label="Search tasks"
                            placeholder="Search tasks"
                            maxLength={255}
                            initialValue={search}
                            onSearch={(value) => updateParams({ q: value, page: null })}
                        />
                    </div>
                    <div role="group" aria-label="Task scope" className="inline-flex rounded-full bg-gray-100 p-1">
                        {scopeOptions.map((option) => (
                            <button
                                key={option.label}
                                type="button"
                                aria-pressed={mine === option.mine}
                                onClick={() => updateParams({ mine: option.mine ? '1' : null, page: null })}
                                className={`rounded-full px-3 py-1 text-sm font-medium transition ${mine === option.mine
                                    ? 'bg-white text-blue-600 shadow-sm'
                                    : 'text-gray-600 hover:text-gray-800'
                                    }`}
                            >
                                {option.label}
                            </button>
                        ))}
                    </div>
                    <div role="group" aria-label="Filter by status" className="flex flex-wrap gap-2">
                        {statusFilters.map((filter) => (
                            <button
                                key={filter.value}
                                type="button"
                                aria-pressed={status === filter.value}
                                onClick={() =>
                                    updateParams({ status: filter.value === 'all' ? null : filter.value, page: null })
                                }
                                className={`rounded-full px-3 py-1 text-sm font-medium transition ${status === filter.value
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