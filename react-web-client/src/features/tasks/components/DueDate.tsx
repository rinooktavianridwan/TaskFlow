import { formatDateTime, isPast } from '@/lib/dates'
import type { Task } from '../types'

type DueDateProps = {
    task: Pick<Task, 'due_date' | 'status'>
    prefix?: boolean
}

export function DueDate({ task, prefix = true }: DueDateProps) {
    if (!task.due_date) return null
    const overdue = task.status !== 'done' && isPast(task.due_date)

    return (
        <span className={overdue ? 'font-semibold text-red-600' : undefined}>
            {prefix && 'Due '}
            {formatDateTime(task.due_date)}
            {overdue && ' (overdue)'}
        </span>
    )
}