// Backend menolak rentang dengan selisih hari >= 31 (maksimal 31 hari inklusif).
export const MAX_RANGE_DAYS = 31

const DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/
const DAY_MS = 86_400_000
// Mencegah tahun setengah jadi (mis. 0002) saat user masih mengetik di input tanggal.
const MIN_YEAR = 1970

// Waktu UTC (ms) untuk tanggal kalender yang valid, atau null.
function parseDate(value: string): number | null {
    if (!DATE_PATTERN.test(value)) return null

    const [year, month, day] = value.split('-').map(Number)
    if (year < MIN_YEAR) return null

    const time = Date.UTC(year, month - 1, day)
    const date = new Date(time)
    // Round-trip menolak tanggal yang "meluber" seperti 2026-02-30.
    if (date.getUTCFullYear() !== year || date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day) {
        return null
    }
    return time
}

// Nilai dari URL tidak bisa dipercaya: hanya tanggal kalender YYYY-MM-DD yang valid yang diterima.
export function normalizeDateParam(value: string | null): string {
    return value !== null && parseDate(value) !== null ? value : ''
}

export type DateRange = { from?: string; to?: string }
export type RangeResult = { range: DateRange; error: string | null }

export function resolveRange(fromParam: string | null, toParam: string | null): RangeResult {
    const from = normalizeDateParam(fromParam)
    const to = normalizeDateParam(toParam)

    // Tanpa `from` backend memakai hari ini; `to` tanpa `from` ditolak backend (422), jadi diabaikan.
    if (from === '') return { range: {}, error: null }
    if (to === '') return { range: { from }, error: null }

    const start = parseDate(from)
    const end = parseDate(to)
    if (start === null || end === null) return { range: {}, error: null }

    const span = (end - start) / DAY_MS
    if (span < 0) {
        return { range: {}, error: 'The end date must not be before the start date.' }
    }
    if (span >= MAX_RANGE_DAYS) {
        return { range: {}, error: `The range can cover at most ${MAX_RANGE_DAYS} days.` }
    }
    return { range: { from, to }, error: null }
}