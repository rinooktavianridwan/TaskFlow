import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { projectKeys } from '@/features/projects/queries'
import type { ProjectRole } from '@/features/projects/types'
import { getMembers, removeMember, updateMemberRole } from './api'
import type { ListMembersParams } from './types'

export const memberKeys = {
    all: ['members'] as const,
    lists: (projectId: number) => [...memberKeys.all, 'list', projectId] as const,
    list: (projectId: number, params: ListMembersParams) => [...memberKeys.lists(projectId), params] as const,
}

type UseMembersOptions = {
    // Hanya untuk halaman daftar (pencarian/pagination). Dibiarkan mati secara default: form task memakai
    // hook yang sama dengan parameter berbeda, dan data placeholder dari daftar lain akan salah.
    keepPreviousData?: boolean
}

export function useMembers(projectId: number, params: ListMembersParams = {}, options: UseMembersOptions = {}) {
    return useQuery({
        queryKey: memberKeys.list(projectId, params),
        queryFn: () => getMembers(projectId, params),
        placeholderData: options.keepPreviousData
            ? (previousData, previousQuery) => (previousQuery?.queryKey[2] === projectId ? previousData : undefined)
            : undefined,
    })
}

export function useUpdateMemberRole(projectId: number) {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: ({ userId, role }: { userId: number; role: ProjectRole }) =>
            updateMemberRole(projectId, userId, role),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: memberKeys.lists(projectId) })
            // `role` pada project adalah peran user yang login; ikut berubah bila yang diubah adalah diri sendiri.
            void queryClient.invalidateQueries({ queryKey: projectKeys.all })
        },
    })
}

export function useRemoveMember(projectId: number) {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: (userId: number) => removeMember(projectId, userId),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: memberKeys.lists(projectId) })
            // Cache detail project sengaja tidak disentuh: bila yang keluar adalah diri sendiri,
            // halaman langsung berpindah, dan refetch detail hanya menghasilkan 403.
            void queryClient.invalidateQueries({ queryKey: projectKeys.lists() })
        },
    })
}