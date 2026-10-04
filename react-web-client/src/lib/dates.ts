const dateFormatter = new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium' })
const dateTimeFormatter = new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium', timeStyle: 'short' })

// Backend mengirim ISO 8601 UTC (...Z); Intl otomatis menampilkannya di zona waktu lokal.
export function formatDate(iso: string): string {
    return dateFormatter.format(new Date(iso))
}

export function formatDateTime(iso: string): string {
    return dateTimeFormatter.format(new Date(iso))
}

export function isPast(iso: string): boolean {
    return new Date(iso).getTime() < Date.now()
}

// Nilai untuk <input type="datetime-local"> adalah waktu LOKAL tanpa zona ("2026-10-05T09:30").
export function toDateTimeLocalValue(iso: string | null): string {
    if (!iso) return ''
    const date = new Date(iso)
    const offsetMs = date.getTimezoneOffset() * 60_000
    return new Date(date.getTime() - offsetMs).toISOString().slice(0, 16)
}
