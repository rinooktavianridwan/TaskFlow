export type ProjectRole = 'owner' | 'editor' | 'viewer'

export type Project = {
    id: number
    name: string
    description: string | null
    // Peran user yang sedang login di project ini. Selalu ada pada endpoint yang kita pakai.
    role: ProjectRole
    created_at: string
    updated_at: string
}

export type ProjectPayload = {
    name: string
    description: string | null
}

export type ListProjectsParams = {
    page?: number
    per_page?: number
    name?: string
}