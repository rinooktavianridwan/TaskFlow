import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { getDailySummary } from './api'
import type { DailySummaryParams } from './types'

export const summaryKeys = {
    all: ['daily-summary'] as const,
    range: (params: DailySummaryParams) => [...summaryKeys.all, params] as const,
}

// `enabled` false bila rentang tanggal tidak valid: request tidak perlu dikirim (backend akan 422).
export function useDailySummary(params: DailySummaryParams, enabled = true) {
    return useQuery({
        queryKey: summaryKeys.range(params),
        queryFn: () => getDailySummary(params),
        enabled,
        placeholderData: keepPreviousData,
    })
}