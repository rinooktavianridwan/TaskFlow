import type { SummaryTask } from './types'

// "date" adalah tanggal kalender (YYYY-MM-DD) zona waktu user. Diformat sebagai UTC agar zona waktu browser tidak menggeser harinya.
export function formatSummaryDate(date: string): string {
    return new Intl.DateTimeFormat('en-GB', { dateStyle: 'full', timeZone: 'UTC' }).format(
        new Date(`${date}T00:00:00Z`),
    )
}

export function formatSummaryRange(from: string, to: string): string {
    return from === to ? formatSummaryDate(from) : `${formatSummaryDate(from)} – ${formatSummaryDate(to)}`
}

// created_at berupa UTC: jam ditampilkan menurut zona waktu dari respons, bukan zona browser.
export function formatSummaryTime(iso: string, timeZone: string): string {
    try {
        return new Intl.DateTimeFormat('en-GB', { timeStyle: 'short', timeZone }).format(new Date(iso))
    } catch {
        // Zona tidak dikenali browser: pakai zona browser daripada membuat halaman error.
        return new Intl.DateTimeFormat('en-GB', { timeStyle: 'short' }).format(new Date(iso))
    }
}

// Contoh: "Completed 2 of 4 checklist items (Design, Review)".
export function describeCompletedItems(task: Pick<SummaryTask, 'checklist' | 'completed_items'>): string | null {
    const count = task.completed_items.length
    if (count === 0) return null

    const names = task.completed_items.join(', ')
    const total = task.checklist?.total ?? 0

    // Total tidak dikenal (task dihapus) atau lebih kecil dari yang selesai (item dihapus belakangan).
    if (total < count) {
        return `Completed ${count} checklist ${count === 1 ? 'item' : 'items'} (${names})`
    }
    return `Completed ${count} of ${total} checklist items (${names})`
}