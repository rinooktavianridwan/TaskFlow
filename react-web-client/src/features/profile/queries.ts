import { useMutation, useQueryClient } from '@tanstack/react-query'
import { authKeys } from '@/features/auth/queries'
import type { User } from '@/features/auth/types'
import { updatePassword, updateProfile } from './api'

export function useUpdateProfile() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: updateProfile,
        onSuccess: (profile) => {
            // Gabungkan ke user di cache auth; field lain (mis. email_verified_at) tidak ikut dikirim PATCH.
            queryClient.setQueryData<User | null>(authKeys.user, (previous) =>
                previous ? { ...previous, name: profile.name, timezone: profile.timezone } : previous,
            )
            // Nama muncul di daftar member/aktivitas dan zona waktu menentukan rangkuman harian,
            // jadi semua cache selain auth ditandai kedaluwarsa.
            void queryClient.invalidateQueries({
                predicate: (query) => query.queryKey[0] !== authKeys.user[0],
            })
        },
    })
}

export function useUpdatePassword() {
    return useMutation({ mutationFn: updatePassword })
}