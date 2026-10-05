import { useDebouncedCallback } from '@/lib/use-debounced-callback'
import { SearchIcon } from './icons'
import { TextField } from './TextField'

type SearchFieldProps = {
    label: string
    placeholder: string
    maxLength: number
    // Nilai awal (dari URL). Kolom tidak dikontrol: ia hanya melaporkan perubahan setelah jeda mengetik.
    initialValue: string
    onSearch: (value: string) => void
}

export function SearchField({ label, placeholder, maxLength, initialValue, onSearch }: SearchFieldProps) {
    const search = useDebouncedCallback((value: string) => onSearch(value.trim()))

    return (
        <TextField
            label={label}
            type="search"
            placeholder={placeholder}
            icon={<SearchIcon />}
            maxLength={maxLength}
            defaultValue={initialValue}
            onChange={(event) => search(event.target.value)}
        />
    )
}