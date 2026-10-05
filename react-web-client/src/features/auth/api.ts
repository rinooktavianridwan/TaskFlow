import { isAxiosError } from 'axios'
import { http, initializeCsrfCookie } from '@/api/http'
import type {
    ForgotPasswordPayload,
    LoginPayload,
    RegisterPayload,
    RegisterResponse,
    ResetPasswordPayload,
    User,
    VerifyRegistrationPayload,
} from './types'

// 401 berarti belum login: itu jawaban yang valid (null), bukan error.
export async function fetchCurrentUser(): Promise<User | null> {
    try {
        const { data } = await http.get<User>('/api/user')
        return data
    } catch (error) {
        if (isAxiosError(error) && error.response?.status === 401) return null
        throw error
    }
}

export async function login(payload: LoginPayload): Promise<void> {
    await initializeCsrfCookie()
    await http.post('/login', payload)
}

export async function logout(): Promise<void> {
    await http.post('/logout')
}

// Tahap 1: simpan data + kirim OTP (202). Akun BELUM dibuat.
export async function register(payload: RegisterPayload): Promise<RegisterResponse> {
    await initializeCsrfCookie()
    const { data } = await http.post<RegisterResponse>('/register', payload)
    return data
}

// Tahap 2: OTP benar -> akun dibuat dan langsung login (204).
export async function verifyRegistration(payload: VerifyRegistrationPayload): Promise<void> {
    await http.post('/register/verify', payload)
}

// Selalu 200 dengan pesan netral, entah email terdaftar atau tidak (agar tidak membocorkan akun yang ada).
export async function forgotPassword(payload: ForgotPasswordPayload): Promise<void> {
    await initializeCsrfCookie()
    await http.post('/forgot-password', payload)
}

// 200 bila berhasil. Token salah/kedaluwarsa/terpakai: 422 key `email`. Konfirmasi tidak cocok: 422 key `password`.
export async function resetPassword(payload: ResetPasswordPayload): Promise<void> {
    await initializeCsrfCookie()
    await http.post('/reset-password', payload)
}