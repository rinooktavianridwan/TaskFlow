import { Link } from 'react-router-dom'
import { ProgressBar } from '@/components/ui/ProgressBar'
import type { Task } from '../types'
import { DueDate } from './DueDate'
import { StatusBadge } from './StatusBadge'

export function TaskRow({ task }: { task: Task }) {
    return (
        <li className="relative rounded-2xl border border-gray-200 bg-white p-4 transition hover:border-blue-300 hover:shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <h3 className="min-w-0 font-semibold break-words text-gray-800">
                    {/* after:inset-0 meluaskan area klik link ke seluruh kartu. */}
                    <Link to={`/tasks/${task.id}`} className="after:absolute after:inset-0">
                        {task.title}
                    </Link>
                </h3>
                <StatusBadge status={task.status} />
            </div>

            {task.description && (
                <p className="mt-1 line-clamp-2 text-sm break-words text-gray-600">{task.description}</p>
            )}

            {/* Tanpa checklist, bar dan ringkasan disembunyikan. */}
            {task.checklist_total > 0 && (
                <div className="mt-3 flex items-center gap-3">
                    <ProgressBar value={task.progress} label={`Progress of ${task.title}`} className="flex-1" />
                    <span className="shrink-0 text-xs text-gray-500 tabular-nums">
                        {task.checklist_done}/{task.checklist_total} · {task.progress}%
                    </span>
                </div>
            )}

            <div className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                <span>{task.assignee ? `Assigned to ${task.assignee.name}` : 'Unassigned'}</span>
                <DueDate task={task} />
            </div>
        </li>
    )
}