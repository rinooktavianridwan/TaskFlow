import type { ProjectRole } from '@/features/projects/types'

export type InvitationStatus = 'pending' | 'accepted' | 'declined'

// Sisi owner: undangan yang dikirim sebuah project (tanpa token).
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

// Pratinjau publik lewat token (tanpa login). Sengaja tanpa token/id/project_id.
export type InvitationPreview = {
    project_name: string
    email: string
    role: ProjectRole
    status: InvitationStatus
    expires_at: string
}

// Undangan milik user yang login. `token` hanya dikembalikan di sini dan dipakai untuk accept/decline.
export type ReceivedInvitation = {
    token: string
    project_name: string
    role: ProjectRole
    status: InvitationStatus
    expires_at: string
    created_at: string
}

export type ListReceivedInvitationsParams = {
    page?: number
    per_page?: number
}