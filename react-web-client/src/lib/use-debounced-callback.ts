import { useCallback, useEffect, useRef } from 'react'

// Menunda pemanggilan fungsi sampai pemanggil berhenti selama `delayMs`.
export function useDebouncedCallback<Args extends unknown[]>(callback: (...args: Args) => void, delayMs = 300) {
    const callbackRef = useRef(callback)
    const timerRef = useRef<ReturnType<typeof setTimeout> | undefined>(undefined)

    // Selalu memakai callback terbaru tanpa membuat fungsi hasil debounce berganti identitas.
    useEffect(() => {
        callbackRef.current = callback
    })

    // Batalkan timer yang menggantung saat komponen dilepas.
    useEffect(() => () => clearTimeout(timerRef.current), [])

    return useCallback(
        (...args: Args) => {
            clearTimeout(timerRef.current)
            timerRef.current = setTimeout(() => callbackRef.current(...args), delayMs)
        },
        [delayMs],
    )
}