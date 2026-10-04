import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { createProject, deleteProject, getProject, getProjects, updateProject } from './api'
import type { ListProjectsParams, ProjectPayload } from './types'

// Semua key diturunkan dari satu akar, sehingga invalidasi bisa menarget "semua daftar"
// tanpa menyentuh cache detail.
export const projectKeys = {
    all: ['projects'] as const,
    lists: () => [...projectKeys.all, 'list'] as const,
    list: (params: ListProjectsParams) => [...projectKeys.lists(), params] as const,
    detail: (id: number) => [...projectKeys.all, 'detail', id] as const,
}

export function useProjects(params: ListProjectsParams) {
    return useQuery({
        queryKey: projectKeys.list(params),
        queryFn: () => getProjects(params),
        // Daftar lama tetap tampil saat halaman/pencarian baru dimuat (tanpa kedipan kosong).
        placeholderData: keepPreviousData,
    })
}

export function useProject(id: number) {
    return useQuery({
        queryKey: projectKeys.detail(id),
        queryFn: () => getProject(id),
        enabled: Number.isInteger(id) && id > 0,
    })
}

export function useCreateProject() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: createProject,
        onSuccess: (project) => {
            queryClient.setQueryData(projectKeys.detail(project.id), project)
            void queryClient.invalidateQueries({ queryKey: projectKeys.lists() })
        },
    })
}

export function useUpdateProject() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: ({ id, payload }: { id: number; payload: ProjectPayload }) => updateProject(id, payload),
        onSuccess: (project) => {
            // PATCH mengembalikan data terbaru, jadi cache detail cukup ditimpa.
            queryClient.setQueryData(projectKeys.detail(project.id), project)
            void queryClient.invalidateQueries({ queryKey: projectKeys.lists() })
        },
    })
}

export function useDeleteProject() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: deleteProject,
        onSuccess: (_, id) => {
            queryClient.removeQueries({ queryKey: projectKeys.detail(id) })
            void queryClient.invalidateQueries({ queryKey: projectKeys.lists() })
        },
    })
}