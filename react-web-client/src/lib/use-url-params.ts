import { useCallback } from 'react'
import { useSearchParams } from 'react-router-dom'

type ParamUpdates = Record<string, string | null>

// Parameter daftar (q, status, page) disimpan di URL: tahan refresh dan bisa dibagikan.
export function useUrlParams() {
    const [searchParams, setSearchParams] = useSearchParams()

    // Semua perubahan digabung dalam SATU pembaruan. setSearchParams tidak mengantre seperti setState,
    // jadi dua pemanggilan berurutan (mis. ubah filter + reset halaman) bisa saling menimpa.
    const update = useCallback(
        (updates: ParamUpdates) => {
            setSearchParams(
                (current) => {
                    const next = new URLSearchParams(current)
                    for (const [key, value] of Object.entries(updates)) {
                        if (value) next.set(key, value)
                        else next.delete(key)
                    }
                    return next
                },
                { replace: true },
            )
        },
        [setSearchParams],
    )

    return [searchParams, update] as const
}

// Nilai dari URL tidak bisa dipercaya: halaman harus bilangan bulat >= 1.
export function parsePage(value: string | null): number {
    const page = Number(value)
    return Number.isInteger(page) && page >= 1 ? page : 1
}

// Halaman 1 tidak perlu ditulis di URL.
export function toPageParam(page: number): string | null {
    return page > 1 ? String(page) : null
}

// Terima nilai hanya bila termasuk pilihan yang dikenal (mis. status filter).
export function parseOption<T extends string>(value: string | null, options: readonly T[]): T | null {
    return options.find((option) => option === value) ?? null
}