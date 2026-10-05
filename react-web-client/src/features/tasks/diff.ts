import type { Task, TaskPayload, UpdateTaskPayload } from './types'

// Dua string waktu dianggap sama bila menunjuk instan yang sama (format ISO bisa beda: "Z" vs "+00:00").
function sameInstant(a: string | null, b: string | null): boolean {
    if (a === null || b === null) return a === b
    return new Date(a).getTime() === new Date(b).getTime()
}

// PATCH hanya mengirim field yang berubah. Ini juga syarat bagi viewer: backend hanya menerima `{status}`.
export function diffTask(task: Task, next: TaskPayload): UpdateTaskPayload {
    const changes: UpdateTaskPayload = {}
    if (next.title !== task.title) changes.title = next.title
    if (next.description !== task.description) changes.description = next.description
    if (next.assigned_to !== task.assigned_to) changes.assigned_to = next.assigned_to
    if (!sameInstant(next.due_date, task.due_date)) changes.due_date = next.due_date
    return changes
}