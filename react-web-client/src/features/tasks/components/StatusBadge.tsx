import { TASK_STATUS_LABELS } from '../status'
import type { TaskStatus } from '../types'

const styles: Record<TaskStatus, string> = {
    todo: 'bg-gray-100 text-gray-600',
    in_progress: 'bg-blue-50 text-blue-700',
    done: 'bg-green-50 text-green-700',
}

export function StatusBadge({ status }: { status: TaskStatus }) {
    return (
        <span className={`shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold ${styles[status]}`}>
            {TASK_STATUS_LABELS[status]}
        </span>
    )
}