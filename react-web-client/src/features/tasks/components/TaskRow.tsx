import { formatDateTime, isPast } from '@/lib/dates'
import type { Task } from '../types'
import { StatusBadge } from './StatusBadge'

export function TaskRow({ task }: { task: Task }) {
    const overdue = task.due_date !== null && task.status !== 'done' && isPast(task.due_date)

    return (
        <li className="rounded-2xl border border-gray-200 bg-white p-4">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <h3 className="min-w-0 font-semibold break-words text-gray-800">{task.title}</h3>
                <StatusBadge status={task.status} />
            </div>

            {task.description && (
                <p className="mt-1 line-clamp-2 text-sm break-words text-gray-600">{task.description}</p>
            )}

            <div className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                <span>{task.assignee ? `Assigned to ${task.assignee.name}` : 'Unassigned'}</span>
                {task.due_date && (
                    <span className={overdue ? 'font-semibold text-red-600' : undefined}>
                        Due {formatDateTime(task.due_date)}
                        {overdue && ' (overdue)'}
                    </span>
                )}
            </div>
        </li>
    )
}