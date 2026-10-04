import { http } from '@/api/http'
import type { ApiResponse, Paginated } from '@/types/api'
import type { ListTasksParams, Task, TaskPayload } from './types'

export async function getProjectTasks(projectId: number, params: ListTasksParams): Promise<Paginated<Task>> {
    const { data } = await http.get<ApiResponse<Paginated<Task>>>(`/api/projects/${projectId}/tasks`, { params })
    return data.data
}

export async function createTask(projectId: number, payload: TaskPayload): Promise<Task> {
    const { data } = await http.post<ApiResponse<Task>>(`/api/projects/${projectId}/tasks`, payload)
    return data.data
}