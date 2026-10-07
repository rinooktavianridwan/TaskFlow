import { useQuery } from '@tanstack/react-query'
import { getProjectActivities } from './api'
import type { ListProjectActivitiesParams } from './types'

export const activityKeys = {
    all: ['activities'] as const,
    lists: (projectId: number) => [...activityKeys.all, 'list', projectId] as const,
    list: (projectId: number, params: ListProjectActivitiesParams) =>
        [...activityKeys.lists(projectId), params] as const,
}

export function useProjectActivities(projectId: number, params: ListProjectActivitiesParams) {
    return useQuery({
        queryKey: activityKeys.list(projectId, params),
        queryFn: () => getProjectActivities(projectId, params),
        // Data lama tetap tampil saat pindah halaman/filter, tetapi hanya dari project yang SAMA.
        placeholderData: (previousData, previousQuery) =>
            previousQuery?.queryKey[2] === projectId ? previousData : undefined,
    })
}