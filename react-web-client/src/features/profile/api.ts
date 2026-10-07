import { http } from '@/api/http'
import type { ApiResponse } from '@/types/api'
import type { Profile, UpdatePasswordPayload, UpdateProfilePayload } from './types'

export async function updateProfile(payload: UpdateProfilePayload): Promise<Profile> {
    const { data } = await http.patch<ApiResponse<Profile>>('/api/profile', payload)
    return data.data
}

// 204 tanpa body. Sesi di perangkat lain tidak dicabut (fitur itu belum ada di backend).
export async function updatePassword(payload: UpdatePasswordPayload): Promise<void> {
    await http.put('/api/profile/password', payload)
}