export type TaskStatus = 'todo' | 'in_progress' | 'done'

export type TaskAssignee = {
    id: number
    name: string
    email: string
}

export type ChecklistItem = {
    id: number
    task_id: number
    title: string
    is_done: boolean
    position: number
    created_at: string
    updated_at: string
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
    // Ringkasan checklist: selalu ada. Tanpa checklist: total 0 dan progress 0.    
    checklist_total: number
    checklist_done: number
    // 0-100, dihitung backend: status done = 100, selain itu done/total.
    progress: number
    // Hanya ada di GET /api/tasks/{id}. Respons daftar, POST, dan PATCH task tidak memuatnya.
    checklist?: ChecklistItem[]
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
    // Backend menerima 1 atau 0 (bukan "true"/"false"). Kita hanya mengirim 1; tanpa filter, param dihilangkan.
    mine?: 1
}

export type ChecklistItemPayload = {
    title: string
}

// Satu field per request: viewer yang ditugaskan hanya boleh mengirim persis `{ is_done }` (403 bila ditambah judul).
export type UpdateChecklistItemPayload = { title: string } | { is_done: boolean }

// POST/PATCH item: `task` sudah final (status, progress, hitungan) dan TIDAK memuat `checklist`.
export type ChecklistMutationResult = {
    item: ChecklistItem
    task: Task
}

// Nilai `action` bisa bertambah di backend, jadi bukan union tertutup. UI menampilkan `description`.
export type TaskActivityAction = string

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