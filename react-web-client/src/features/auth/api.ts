import { isAxiosError } from 'axios'
import { http, initializeCsrfCookie } from '@/api/http'
import type {
    LoginPayload,
    RegisterPayload,
    RegisterResponse,
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