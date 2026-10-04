import { useQuery, useQueryClient } from '@tanstack/react-query'
import { isAxiosError } from 'axios'
import type { ReactNode } from 'react'
import {
    login as loginRequest,
    logout as logoutRequest,
    verifyRegistration as verifyRegistrationRequest,
} from './api'
import { AuthContext } from './auth-context'
import { authKeys, currentUserQueryOptions } from './queries'
import type { LoginPayload, VerifyRegistrationPayload } from './types'

export function AuthProvider({ children }: { children: ReactNode }) {
    const queryClient = useQueryClient()
    const { data: user = null, isPending } = useQuery(currentUserQueryOptions)

    async function login(payload: LoginPayload) {
        await loginRequest(payload)
        // Muat ulang user; guard otomatis mengarahkan keluar dari halaman login.
        await queryClient.invalidateQueries({ queryKey: authKeys.user })
    }

    // Verifikasi OTP yang berhasil = akun dibuat + sesi aktif, jadi sama seperti login.
    async function verifyRegistration(payload: VerifyRegistrationPayload) {
        await verifyRegistrationRequest(payload)
        await queryClient.invalidateQueries({ queryKey: authKeys.user })
    }

    async function logout() {
        try {
            await logoutRequest()
        } catch (error) {
            // 401 = sesi sudah habis, tujuan logout tetap tercapai.
            if (!(isAxiosError(error) && error.response?.status === 401)) throw error
        }
        queryClient.setQueryData(authKeys.user, null)
        // Buang cache milik user sebelumnya agar tidak bocor ke user berikutnya.
        queryClient.removeQueries({
            predicate: (query) => query.queryKey[0] !== authKeys.user[0],
        })
    }

    return (
        <AuthContext value={{ user, isLoading: isPending, login, verifyRegistration, logout }}>
            {children}
        </AuthContext>
    )
}