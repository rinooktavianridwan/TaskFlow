import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { projectKeys } from '@/features/projects/queries'
import {
    acceptInvitation,
    createInvitation,
    declineInvitation,
    getInvitationPreview,
    getInvitations,
    getReceivedInvitations,
    revokeInvitation,
} from './api'
import type { CreateInvitationPayload, ListInvitationsParams, ListReceivedInvitationsParams } from './types'

export const invitationKeys = {
    all: ['invitations'] as const,
    // Sisi owner: undangan yang dikirim sebuah project.
    lists: (projectId: number) => [...invitationKeys.all, 'list', projectId] as const,
    list: (projectId: number, params: ListInvitationsParams) => [...invitationKeys.lists(projectId), params] as const,
    // Sisi penerima.
    preview: (token: string) => [...invitationKeys.all, 'preview', token] as const,
    received: () => [...invitationKeys.all, 'received'] as const,
    receivedList: (params: ListReceivedInvitationsParams) => [...invitationKeys.received(), params] as const,
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

export function useInvitationPreview(token: string) {
    return useQuery({
        queryKey: invitationKeys.preview(token),
        queryFn: () => getInvitationPreview(token),
    })
}

export function useReceivedInvitations(params: ListReceivedInvitationsParams) {
    return useQuery({
        queryKey: invitationKeys.receivedList(params),
        queryFn: () => getReceivedInvitations(params),
        placeholderData: keepPreviousData,
    })
}

// Accept dan decline sama-sama membuat undangan tidak lagi pending.
// Hanya accept yang mengubah daftar project (user menjadi member).
export function useAcceptInvitation() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: (token: string) => acceptInvitation(token),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: invitationKeys.received() })
            void queryClient.invalidateQueries({ queryKey: projectKeys.lists() })
        },
        // Termasuk saat gagal (mis. 422 kedaluwarsa): status di pratinjau mungkin sudah berubah.
        onSettled: (_data, _error, token) => {
            void queryClient.invalidateQueries({ queryKey: invitationKeys.preview(token) })
        },
    })
}

export function useDeclineInvitation() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: (token: string) => declineInvitation(token),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: invitationKeys.received() })
        },
        onSettled: (_data, _error, token) => {
            void queryClient.invalidateQueries({ queryKey: invitationKeys.preview(token) })
        },
    })
}