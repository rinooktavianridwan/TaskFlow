export type SummaryEvent = {
    id: number
    action: string
    description: string
    metadata: Record<string, unknown> | null
    // UTC (ISO 8601): tampilkan dengan zona waktu `DailySummary.timezone`.
    created_at: string
}

export type SummaryTask = {
    task_id: number
    task_title: string
    // false bila task sudah dihapus.
    task_exists: boolean
    // Kondisi SAAT INI; null bila task dihapus.
    checklist: { total: number; done: number } | null
    // Item yang diselesaikan user pada hari itu (dicentang lalu dibuka lagi tidak termasuk).
    completed_items: string[]
    events: SummaryEvent[]
}

export type SummaryProject = {
    project_id: number
    project_name: string
    events: SummaryEvent[]
    tasks: SummaryTask[]
}

export type SummaryDay = {
    date: string
    total_events: number
    projects: SummaryProject[]
}

export type DailySummary = {
    timezone: string
    from: string
    to: string
    // Terbaru dulu; hari tanpa aktivitas tidak ada di array.
    days: SummaryDay[]
}

export type DailySummaryParams = {
    from?: string
    to?: string
}