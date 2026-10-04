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

export type ListTasksParams = {
    page?: number
    per_page?: number
    title?: string
    status?: TaskStatus
}