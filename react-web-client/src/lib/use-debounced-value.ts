import { useEffect, useState } from 'react'

// Menunda perubahan nilai agar kolom pencarian tidak menembak API di setiap ketikan.
export function useDebouncedValue<T>(value: T, delayMs = 300): T {
    const [debounced, setDebounced] = useState(value)

    useEffect(() => {
        const timer = setTimeout(() => setDebounced(value), delayMs)
        return () => clearTimeout(timer)
    }, [value, delayMs])

    return debounced
}