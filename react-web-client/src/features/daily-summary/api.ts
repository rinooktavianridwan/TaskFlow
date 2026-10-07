import { http } from '@/api/http'
import type { ApiResponse } from '@/types/api'
import type { DailySummary, DailySummaryParams } from './types'

// Tanpa parameter = hari ini menurut zona waktu user. Hanya `from` = satu hari itu saja.
export async function getDailySummary(params: DailySummaryParams): Promise<DailySummary> {
    const { data } = await http.get<ApiResponse<DailySummary>>('/api/me/daily-summary', { params })
    return data.data
}