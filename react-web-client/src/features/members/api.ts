import { http } from '@/api/http'
import type { ApiResponse, Paginated } from '@/types/api'
import type { ListMembersParams, Member } from './types'

export async function getMembers(projectId: number, params: ListMembersParams): Promise<Paginated<Member>> {
    const { data } = await http.get<ApiResponse<Paginated<Member>>>(`/api/projects/${projectId}/members`, { params })
    return data.data
}