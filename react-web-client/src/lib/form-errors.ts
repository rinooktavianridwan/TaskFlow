import { isAxiosError } from 'axios'
import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'
import type { ApiErrorBody } from '@/types/api'

export function getErrorMessage(error: unknown): string {
    if (isAxiosError<ApiErrorBody>(error)) {
        const data = error.response?.data
        const firstFieldError = data?.errors ? Object.values(data.errors)[0]?.[0] : undefined
        return firstFieldError ?? data?.message ?? 'Unable to reach the server.'
    }
    return 'Something went wrong. Please try again.'
}

// Petakan 422 Laravel ke field form. Return true bila ada yang dipetakan.
export function applyFieldErrors<T extends FieldValues>(
    error: unknown,
    setError: UseFormSetError<T>,
): boolean {
    if (!isAxiosError<ApiErrorBody>(error) || error.response?.status !== 422) return false

    const errors = error.response.data?.errors
    if (!errors) return false

    for (const [field, messages] of Object.entries(errors)) {
        setError(field as Path<T>, { type: 'server', message: messages[0] })
    }
    return true
}

// Pesan 422 untuk satu field tertentu. Berguna saat field itu bukan input di form (mis. `email` pada reset password).
export function getFieldError(error: unknown, field: string): string | undefined {
    if (!isAxiosError<ApiErrorBody>(error) || error.response?.status !== 422) return undefined
    return error.response.data?.errors?.[field]?.[0]
}

export function getErrorStatus(error: unknown): number | undefined {
    return isAxiosError(error) ? error.response?.status : undefined
}