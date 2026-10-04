export type TaskStatus = 'todo' | 'in_progress' | 'done'

export type TaskAssignee = {
    id: number
    name: string
    email: string
}

export type Task = {
    id: number
    project_id: number
    title: string
    description: string | null
    status: TaskStatus
    assigned_to: number | null
    assignee: TaskAssignee | null
    due_date: string | null
    created_at: string
    updated_at: string
}

export type TaskPayload = {
    title: string
    description: string | null
    assigned_to: number | null
    due_date: string | null
}

// PATCH: semua field opsional, hanya yang dikirim yang diubah.
export type UpdateTaskPayload = Partial<TaskPayload> & { status?: TaskStatus }

export type ListTasksParams = {
    page?: number
    per_page?: number
    title?: string
    status?: TaskStatus
}

export type TaskActivityAction = 'created' | 'status_changed' | 'assigned' | 'updated'

export type TaskActivity = {
    id: number
    task_id: number
    action: TaskActivityAction
    description: string
    user: { id: number; name: string } | null
    created_at: string
}

export type ListTaskActivitiesParams = {
    page?: number
    per_page?: number
}