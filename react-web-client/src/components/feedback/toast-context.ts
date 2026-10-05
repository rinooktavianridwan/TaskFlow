import { createContext, useContext } from 'react'

export type ToastVariant = 'success' | 'error'

export type ToastContextValue = {
    showToast: (message: string, variant?: ToastVariant) => void
}

export const ToastContext = createContext<ToastContextValue | null>(null)

export function useToast(): ToastContextValue {
    const value = useContext(ToastContext)
    if (!value) throw new Error('useToast harus dipakai di dalam ToastProvider.')
    return value
}