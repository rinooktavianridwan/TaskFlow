import type { ProjectRole } from '@/features/projects/types'

export type Member = {
    user_id: number
    name: string
    email: string
    role: ProjectRole
}

export type ListMembersParams = {
    page?: number
    per_page?: number
    name?: string
}