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