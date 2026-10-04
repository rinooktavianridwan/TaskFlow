import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { createTask, getProjectTasks } from './api'
import type { ListTasksParams, TaskPayload } from './types'

export const taskKeys = {
    all: ['tasks'] as const,
    lists: (projectId: number) => [...taskKeys.all, 'list', projectId] as const,
    list: (projectId: number, params: ListTasksParams) => [...taskKeys.lists(projectId), params] as const,
    detail: (taskId: number) => [...taskKeys.all, 'detail', taskId] as const,
}

export function useProjectTasks(projectId: number, params: ListTasksParams) {
    return useQuery({
        queryKey: taskKeys.list(projectId, params),
        queryFn: () => getProjectTasks(projectId, params),
        // Tampilkan data lama saat pindah halaman/filter, tetapi hanya dari project yang SAMA.
        // keepPreviousData polos akan menampilkan task project lain saat berpindah project.
        placeholderData: (previousData, previousQuery) =>
            previousQuery?.queryKey[2] === projectId ? previousData : undefined,
    })
}

export function useCreateTask(projectId: number) {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: (payload: TaskPayload) => createTask(projectId, payload),
        onSuccess: (task) => {
            queryClient.setQueryData(taskKeys.detail(task.id), task)
            void queryClient.invalidateQueries({ queryKey: taskKeys.lists(projectId) })
        },
    })
}