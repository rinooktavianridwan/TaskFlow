import type { ProjectRole } from '@/features/projects/types'

export type InvitationStatus = 'pending' | 'accepted' | 'declined'

export type Invitation = {
    id: number
    email: string
    role: ProjectRole
    status: InvitationStatus
    expires_at: string
    created_at: string
}

export type CreateInvitationPayload = {
    email: string
    role: ProjectRole
}

export type ListInvitationsParams = {
    page?: number
    per_page?: number
    status?: InvitationStatus
}