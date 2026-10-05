import { http } from '@/api/http'
import type { ApiResponse, Paginated } from '@/types/api'
import type {
    CreateInvitationPayload,
    Invitation,
    InvitationPreview,
    ListInvitationsParams,
    ListReceivedInvitationsParams,
    ReceivedInvitation,
} from './types'

export async function getInvitations(
    projectId: number,
    params: ListInvitationsParams,
): Promise<Paginated<Invitation>> {
    const { data } = await http.get<ApiResponse<Paginated<Invitation>>>(`/api/projects/${projectId}/invitations`, {
        params,
    })
    return data.data
}

export async function createInvitation(projectId: number, payload: CreateInvitationPayload): Promise<Invitation> {
    const { data } = await http.post<ApiResponse<Invitation>>(`/api/projects/${projectId}/invitations`, payload)
    return data.data
}

export async function revokeInvitation(projectId: number, invitationId: number): Promise<void> {
    await http.delete(`/api/projects/${projectId}/invitations/${invitationId}`)
}

// Publik (tanpa login): untuk halaman tautan di email undangan.
export async function getInvitationPreview(token: string): Promise<InvitationPreview> {
    const { data } = await http.get<ApiResponse<InvitationPreview>>(`/api/invitations/${encodeURIComponent(token)}`)
    return data.data
}

// Undangan pending milik user yang login (email akun = email undangan).
export async function getReceivedInvitations(
    params: ListReceivedInvitationsParams,
): Promise<Paginated<ReceivedInvitation>> {
    const { data } = await http.get<ApiResponse<Paginated<ReceivedInvitation>>>('/api/invitations', { params })
    return data.data
}

export async function acceptInvitation(token: string): Promise<void> {
    await http.post(`/api/invitations/${encodeURIComponent(token)}/accept`)
}

export async function declineInvitation(token: string): Promise<void> {
    await http.post(`/api/invitations/${encodeURIComponent(token)}/decline`)
}