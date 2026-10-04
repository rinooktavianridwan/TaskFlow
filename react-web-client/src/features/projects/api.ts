import { http } from '@/api/http'
import type { ApiResponse, Paginated } from '@/types/api'
import type { ListProjectsParams, Project, ProjectPayload } from './types'

export async function getProjects(params: ListProjectsParams): Promise<Paginated<Project>> {
    const { data } = await http.get<ApiResponse<Paginated<Project>>>('/api/projects', { params })
    return data.data
}

export async function getProject(id: number): Promise<Project> {
    const { data } = await http.get<ApiResponse<Project>>(`/api/projects/${id}`)
    return data.data
}

export async function createProject(payload: ProjectPayload): Promise<Project> {
    const { data } = await http.post<ApiResponse<Project>>('/api/projects', payload)
    return data.data
}

export async function updateProject(id: number, payload: ProjectPayload): Promise<Project> {
    const { data } = await http.patch<ApiResponse<Project>>(`/api/projects/${id}`, payload)
    return data.data
}

export async function deleteProject(id: number): Promise<void> {
    await http.delete(`/api/projects/${id}`)
}