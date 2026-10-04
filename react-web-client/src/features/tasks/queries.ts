import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
    createTask,
    deleteTask,
    getProjectTasks,
    getTask,
    getTaskActivities,
    updateTask,
} from './api'
import type {
    ListTaskActivitiesParams,
    ListTasksParams,
    Task,
    TaskPayload,
    UpdateTaskPayload,
} from './types'

export const taskKeys = {
    all: ['tasks'] as const,
    lists: (projectId: number) => [...taskKeys.all, 'list', projectId] as const,
    list: (projectId: number, params: ListTasksParams) => [...taskKeys.lists(projectId), params] as const,
    detail: (taskId: number) => [...taskKeys.all, 'detail', taskId] as const,
    activities: (taskId: number) => [...taskKeys.detail(taskId), 'activities'] as const,
    activityList: (taskId: number, params: ListTaskActivitiesParams) =>
        [...taskKeys.activities(taskId), params] as const,
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

export function useTask(id: number) {
    return useQuery({
        queryKey: taskKeys.detail(id),
        queryFn: () => getTask(id),
        enabled: Number.isInteger(id) && id > 0,
    })
}

export function useTaskActivities(taskId: number, params: ListTaskActivitiesParams) {
    return useQuery({
        queryKey: taskKeys.activityList(taskId, params),
        queryFn: () => getTaskActivities(taskId, params),
        placeholderData: (previousData, previousQuery) =>
            previousQuery?.queryKey[2] === taskId ? previousData : undefined,
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

export function useUpdateTask() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: ({ id, payload }: { id: number; payload: UpdateTaskPayload }) => updateTask(id, payload),
        onSuccess: (task) => {
            // PATCH mengembalikan data terbaru; riwayat aktivitas bertambah, jadi ambil ulang.
            queryClient.setQueryData(taskKeys.detail(task.id), task)
            void queryClient.invalidateQueries({ queryKey: taskKeys.activities(task.id) })
            void queryClient.invalidateQueries({ queryKey: taskKeys.lists(task.project_id) })
        },
    })
}

export function useDeleteTask() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: (task: Task) => deleteTask(task.id),
        onSuccess: (_, task) => {
            // Cache detail sengaja tidak dibuang: halaman detail masih terpasang sampai navigasi selesai,
            // dan membuangnya memicu request 404 yang tidak perlu.
            void queryClient.invalidateQueries({ queryKey: taskKeys.lists(task.project_id) })
        },
    })
}