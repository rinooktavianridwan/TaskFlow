import { http } from '@/api/http'
import type { ApiResponse, Paginated } from '@/types/api'
import type { ListProjectActivitiesParams, ProjectActivity } from './types'

// Khusus owner (selain itu 403). Urut terbaru di atas.
export async function getProjectActivities(
    projectId: number,
    params: ListProjectActivitiesParams,
): Promise<Paginated<ProjectActivity>> {
    const { data } = await http.get<ApiResponse<Paginated<ProjectActivity>>>(
        `/api/projects/${projectId}/activities`,
        { params },
    )
    return data.data
}