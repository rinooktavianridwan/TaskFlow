import { useQuery } from '@tanstack/react-query'
import { getMembers } from './api'
import type { ListMembersParams } from './types'

export const memberKeys = {
    all: ['members'] as const,
    lists: (projectId: number) => [...memberKeys.all, 'list', projectId] as const,
    list: (projectId: number, params: ListMembersParams) => [...memberKeys.lists(projectId), params] as const,
}

export function useMembers(projectId: number, params: ListMembersParams = {}) {
    return useQuery({
        queryKey: memberKeys.list(projectId, params),
        queryFn: () => getMembers(projectId, params),
    })
}