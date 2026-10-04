import type { TaskStatus } from './types'

export const TASK_STATUSES: TaskStatus[] = ['todo', 'in_progress', 'done']

export const TASK_STATUS_LABELS: Record<TaskStatus, string> = {
    todo: 'To do',
    in_progress: 'In progress',
    done: 'Done',
}