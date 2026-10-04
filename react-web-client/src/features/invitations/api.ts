import { http } from '@/api/http'
import type { ApiResponse, Paginated } from '@/types/api'
import type { CreateInvitationPayload, Invitation, ListInvitationsParams } from './types'

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