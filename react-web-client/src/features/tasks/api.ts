import { http } from '@/api/http'
import type { ApiResponse, Paginated } from '@/types/api'
import type {
    ListTaskActivitiesParams,
    ListTasksParams,
    Task,
    TaskActivity,
    TaskPayload,
    UpdateTaskPayload,
} from './types'

export async function getProjectTasks(projectId: number, params: ListTasksParams): Promise<Paginated<Task>> {
    const { data } = await http.get<ApiResponse<Paginated<Task>>>(`/api/projects/${projectId}/tasks`, { params })
    return data.data
}

export async function createTask(projectId: number, payload: TaskPayload): Promise<Task> {
    const { data } = await http.post<ApiResponse<Task>>(`/api/projects/${projectId}/tasks`, payload)
    return data.data
}

export async function getTask(id: number): Promise<Task> {
    const { data } = await http.get<ApiResponse<Task>>(`/api/tasks/${id}`)
    return data.data
}

export async function updateTask(id: number, payload: UpdateTaskPayload): Promise<Task> {
    const { data } = await http.patch<ApiResponse<Task>>(`/api/tasks/${id}`, payload)
    return data.data
}

export async function deleteTask(id: number): Promise<void> {
    await http.delete(`/api/tasks/${id}`)
}

export async function getTaskActivities(
    taskId: number,
    params: ListTaskActivitiesParams,
): Promise<Paginated<TaskActivity>> {
    const { data } = await http.get<ApiResponse<Paginated<TaskActivity>>>(`/api/tasks/${taskId}/activities`, { params })
    return data.data
}