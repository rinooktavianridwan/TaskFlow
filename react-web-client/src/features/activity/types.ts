export type ProjectActivity = {
    id: number
    project_id: number
    // Bisa menunjuk task yang sudah dihapus.
    task_id: number | null
    // Daftar nilai bisa bertambah di backend: jangan bergantung pada union tertutup.
    action: string
    description: string
    metadata: Record<string, unknown> | null
    // Bernama `actor` (di riwayat task: `user`). Null bila user sudah dihapus.
    actor: { id: number; name: string } | null
    created_at: string
}

export type ListProjectActivitiesParams = {
    page?: number
    per_page?: number
    action?: string
    actor_id?: number
    task_id?: number
}