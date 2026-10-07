import { describe, expect, it } from 'vitest'
import { describeCompletedItems, formatSummaryDate, formatSummaryRange, formatSummaryTime } from './format'

describe('formatSummaryDate', () => {
    it('formats a calendar date without shifting the day', () => {
        const text = formatSummaryDate('2026-10-07')
        expect(text).toContain('7 October 2026')
    })
})

describe('formatSummaryRange', () => {
    it('shows one date for a single day and both ends for a range', () => {
        expect(formatSummaryRange('2026-10-07', '2026-10-07')).toBe(formatSummaryDate('2026-10-07'))
        expect(formatSummaryRange('2026-10-01', '2026-10-07')).toContain(' – ')
    })
})

describe('formatSummaryTime', () => {
    it('renders the time in the given timezone', () => {
        expect(formatSummaryTime('2026-10-07T17:30:00Z', 'UTC')).toBe('17:30')
        expect(formatSummaryTime('2026-10-07T17:30:00Z', 'Asia/Jakarta')).toBe('00:30')
    })

    it('does not throw for an unknown timezone', () => {
        expect(formatSummaryTime('2026-10-07T17:30:00Z', 'Not/AZone')).toMatch(/^\d{2}:\d{2}$/)
    })
})

describe('describeCompletedItems', () => {
    it('returns null when nothing was completed', () => {
        expect(describeCompletedItems({ checklist: { total: 4, done: 1 }, completed_items: [] })).toBeNull()
    })

    it('describes progress against the current checklist size', () => {
        expect(
            describeCompletedItems({ checklist: { total: 4, done: 2 }, completed_items: ['Design', 'Review'] }),
        ).toBe('Completed 2 of 4 checklist items (Design, Review)')
    })

    it('omits the total when the task was deleted', () => {
        expect(describeCompletedItems({ checklist: null, completed_items: ['Design'] })).toBe(
            'Completed 1 checklist item (Design)',
        )
        expect(describeCompletedItems({ checklist: null, completed_items: ['A', 'B'] })).toBe(
            'Completed 2 checklist items (A, B)',
        )
    })

    it('omits the total when it is smaller than the completed count', () => {
        expect(describeCompletedItems({ checklist: { total: 1, done: 1 }, completed_items: ['A', 'B'] })).toBe(
            'Completed 2 checklist items (A, B)',
        )
    })
})