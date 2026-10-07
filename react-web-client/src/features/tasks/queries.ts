import { useMutation, useQuery, useQueryClient, type QueryClient } from '@tanstack/react-query'
import { projectKeys } from '@/features/projects/queries'
import {
    addChecklistItem,
    createTask,
    deleteChecklistItem,
    deleteTask,
    getProjectTasks,
    getTask,
    getTaskActivities,
    updateChecklistItem,
    updateTask,
} from './api'
import type {
    ChecklistItem,
    ChecklistItemPayload,
    ChecklistMutationResult,
    ListTaskActivitiesParams,
    ListTasksParams,
    Task,
    TaskPayload,
    UpdateChecklistItemPayload,
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

// Respons task selain GET detail tidak memuat `checklist`. Menimpa cache detail begitu saja akan
function keepChecklist(task: Task, previous: Task | undefined): Task {
    return previous?.checklist ? { ...task, checklist: previous.checklist } : task
}

function mergeChecklistItem(items: ChecklistItem[], item: ChecklistItem): ChecklistItem[] {
    const exists = items.some((current) => current.id === item.id)
    const next = exists ? items.map((current) => (current.id === item.id ? item : current)) : [...items, item]
    return next.sort((a, b) => a.position - b.position)
}

// POST/PATCH item mengembalikan Task final (status, progress, hitungan) tanpa checklist:
function syncTaskDetail(queryClient: QueryClient, { item, task }: ChecklistMutationResult) {
    queryClient.setQueryData<Task>(taskKeys.detail(task.id), (previous) => {
        if (!previous) return previous
        return { ...task, checklist: mergeChecklistItem(previous.checklist ?? [], item) }
    })
}

// Perubahan task/checklist memengaruhi progress project (rata-rata progress semua task).
function invalidateProjects(queryClient: QueryClient) {
    void queryClient.invalidateQueries({ queryKey: projectKeys.all })
}

export function useProjectTasks(projectId: number, params: ListTasksParams) {
    return useQuery({
        queryKey: taskKeys.list(projectId, params),
        queryFn: () => getProjectTasks(projectId, params),
        // Tampilkan data lama saat pindah halaman/filter, tetapi hanya dari project yang SAMA.
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
            // Task baru belum punya item, jadi daftar checklist kosong sudah benar.
            queryClient.setQueryData<Task>(taskKeys.detail(task.id), { ...task, checklist: [] })
            void queryClient.invalidateQueries({ queryKey: taskKeys.lists(projectId) })
            invalidateProjects(queryClient)
        },
    })
}

export function useUpdateTask() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: ({ id, payload }: { id: number; payload: UpdateTaskPayload }) => updateTask(id, payload),
        onSuccess: (task) => {
            // PATCH mengembalikan data terbaru; riwayat aktivitas bertambah, jadi ambil ulang.
            queryClient.setQueryData<Task>(taskKeys.detail(task.id), (previous) => keepChecklist(task, previous))
            void queryClient.invalidateQueries({ queryKey: taskKeys.activities(task.id) })
            void queryClient.invalidateQueries({ queryKey: taskKeys.lists(task.project_id) })
            invalidateProjects(queryClient)
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
            invalidateProjects(queryClient)
        },
    })
}

export function useAddChecklistItem(taskId: number) {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: (payload: ChecklistItemPayload) => addChecklistItem(taskId, payload),
        onSuccess: (result) => {
            syncTaskDetail(queryClient, result)
            void queryClient.invalidateQueries({ queryKey: taskKeys.lists(result.task.project_id) })
            void queryClient.invalidateQueries({ queryKey: taskKeys.activities(result.task.id) })
            invalidateProjects(queryClient)
        },
    })
}

export function useUpdateChecklistItem() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: ({ itemId, payload }: { itemId: number; payload: UpdateChecklistItemPayload }) =>
            updateChecklistItem(itemId, payload),
        onSuccess: (result) => {
            syncTaskDetail(queryClient, result)
            void queryClient.invalidateQueries({ queryKey: taskKeys.lists(result.task.project_id) })
            void queryClient.invalidateQueries({ queryKey: taskKeys.activities(result.task.id) })
            invalidateProjects(queryClient)
        },
    })
}

export function useDeleteChecklistItem(task: Pick<Task, 'id' | 'project_id'>) {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: (itemId: number) => deleteChecklistItem(itemId),
        onSuccess: () => {
            // 204 tanpa body: status/progress bisa berubah, jadi ambil ulang task (sekaligus riwayatnya, karena key aktivitas berada di bawah key detail).
            void queryClient.invalidateQueries({ queryKey: taskKeys.detail(task.id) })
            void queryClient.invalidateQueries({ queryKey: taskKeys.lists(task.project_id) })
            invalidateProjects(queryClient)
        },
    })
}