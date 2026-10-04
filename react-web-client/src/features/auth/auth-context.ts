import { createContext, useContext } from 'react'
import type { LoginPayload, User, VerifyRegistrationPayload } from './types'

export type AuthContextValue = {
    user: User | null
    isLoading: boolean
    login: (payload: LoginPayload) => Promise<void>
    verifyRegistration: (payload: VerifyRegistrationPayload) => Promise<void>
    logout: () => Promise<void>
}

export const AuthContext = createContext<AuthContextValue | null>(null)

export function useAuth(): AuthContextValue {
    const value = useContext(AuthContext)
    if (!value) throw new Error('useAuth harus dipakai di dalam AuthProvider.')
    return value
}