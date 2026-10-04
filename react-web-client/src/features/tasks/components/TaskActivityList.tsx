import { useState } from 'react'
import { ErrorState, LoadingState } from '@/components/feedback/states'
import { Pagination } from '@/components/ui/Pagination'
import { formatDateTime } from '@/lib/dates'
import { getErrorMessage } from '@/lib/form-errors'
import { useTaskActivities } from '../queries'

export function TaskActivityList({ taskId }: { taskId: number }) {
    const [page, setPage] = useState(1)
    const { data, isPending, isError, error, refetch, isPlaceholderData } = useTaskActivities(taskId, { page })

    if (isPending) return <LoadingState />
    if (isError) return <ErrorState message={getErrorMessage(error)} onRetry={() => void refetch()} />
    if (data.items.length === 0) return <p className="text-sm text-gray-500">No activity yet.</p>

    return (
        <div className={isPlaceholderData ? 'opacity-60 transition-opacity' : 'transition-opacity'}>
            <ol className="space-y-4 border-l-2 border-gray-100 pl-5">
                {data.items.map((activity) => (
                    <li key={activity.id} className="relative">
                        <span
                            aria-hidden="true"
                            className="absolute top-1.5 -left-[26px] h-2.5 w-2.5 rounded-full bg-blue-400"
                        />
                        <p className="text-sm text-gray-800">{activity.description}</p>
                        <p className="text-xs text-gray-400">
                            {activity.user?.name ?? 'System'} · {formatDateTime(activity.created_at)}
                        </p>
                    </li>
                ))}
            </ol>
            <Pagination meta={data.meta} onPageChange={setPage} />
        </div>
    )
}