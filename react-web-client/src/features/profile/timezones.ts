function listSupportedZones(): string[] {
    try {
        return Intl.supportedValuesOf('timeZone')
    } catch {
        // Browser lama tanpa Intl.supportedValuesOf: dropdown tetap berisi UTC dan zona saat ini.
        return []
    }
}

// Pilihan dropdown zona waktu. "UTC" tidak selalu ada di daftar browser, dan nilai user saat ini
// harus selalu ada agar <select> tidak menampilkan pilihan yang salah.
export function getTimezoneOptions(current: string): string[] {
    const zones = new Set<string>(['UTC', ...listSupportedZones()])
    zones.add(current)
    return [...zones].sort((a, b) => a.localeCompare(b))
}

export function detectBrowserTimezone(): string | null {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || null
    } catch {
        return null
    }
}