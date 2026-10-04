const dateFormatter = new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium' })

// Backend mengirim ISO 8601 UTC (...Z); Intl otomatis menampilkannya di zona waktu lokal.
export function formatDate(iso: string): string {
    return dateFormatter.format(new Date(iso))
}