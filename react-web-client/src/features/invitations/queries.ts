import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { createInvitation, getInvitations, revokeInvitation } from './api'
import type { CreateInvitationPayload, ListInvitationsParams } from './types'

export const invitationKeys = {
    all: ['invitations'] as const,
    lists: (projectId: number) => [...invitationKeys.all, 'list', projectId] as const,
    list: (projectId: number, params: ListInvitationsParams) => [...invitationKeys.lists(projectId), params] as const,
}

export function useInvitations(projectId: number, params: ListInvitationsParams) {
    return useQuery({
        queryKey: invitationKeys.list(projectId, params),
        queryFn: () => getInvitations(projectId, params),
        // Tampilkan data lama saat pindah halaman/filter, tetapi hanya dari project yang SAMA.
        placeholderData: (previousData, previousQuery) =>
            previousQuery?.queryKey[2] === projectId ? previousData : undefined,
    })
}

export function useCreateInvitation(projectId: number) {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: (payload: CreateInvitationPayload) => createInvitation(projectId, payload),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: invitationKeys.lists(projectId) })
        },
    })
}

export function useRevokeInvitation(projectId: number) {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: (invitationId: number) => revokeInvitation(projectId, invitationId),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: invitationKeys.lists(projectId) })
        },
    })
}