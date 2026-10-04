import { http } from '@/api/http'
import type { ProjectRole } from '@/features/projects/types'
import type { ApiResponse, Paginated } from '@/types/api'
import type { ListMembersParams, Member } from './types'

export async function getMembers(projectId: number, params: ListMembersParams): Promise<Paginated<Member>> {
    const { data } = await http.get<ApiResponse<Paginated<Member>>>(`/api/projects/${projectId}/members`, { params })
    return data.data
}

export async function updateMemberRole(projectId: number, userId: number, role: ProjectRole): Promise<Member> {
    const { data } = await http.patch<ApiResponse<Member>>(`/api/projects/${projectId}/members/${userId}`, { role })
    return data.data
}

export async function removeMember(projectId: number, userId: number): Promise<void> {
    await http.delete(`/api/projects/${projectId}/members/${userId}`)
}