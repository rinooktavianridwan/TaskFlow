import { describe, expect, it } from 'vitest'
import { normalizeDateParam, resolveRange } from './range'

describe('normalizeDateParam', () => {
    it('keeps valid calendar dates', () => {
        expect(normalizeDateParam('2026-10-07')).toBe('2026-10-07')
    })

    it('rejects malformed, impossible and half-typed dates', () => {
        expect(normalizeDateParam(null)).toBe('')
        expect(normalizeDateParam('abc')).toBe('')
        expect(normalizeDateParam('2026-1-5')).toBe('')
        expect(normalizeDateParam('2026-02-30')).toBe('')
        expect(normalizeDateParam('0002-10-07')).toBe('')
    })
})

describe('resolveRange', () => {
    it('sends nothing (today) when no dates are set', () => {
        expect(resolveRange(null, null)).toEqual({ range: {}, error: null })
    })

    it('sends only `from` for a single day', () => {
        expect(resolveRange('2026-10-07', null)).toEqual({ range: { from: '2026-10-07' }, error: null })
    })

    it('ignores `to` when `from` is missing', () => {
        expect(resolveRange(null, '2026-10-07')).toEqual({ range: {}, error: null })
    })

    it('ignores invalid values', () => {
        expect(resolveRange('2026-02-30', '2026-03-01')).toEqual({ range: {}, error: null })
    })

    it('accepts a normal range and a same-day range', () => {
        expect(resolveRange('2026-10-01', '2026-10-07')).toEqual({
            range: { from: '2026-10-01', to: '2026-10-07' },
            error: null,
        })
        expect(resolveRange('2026-10-07', '2026-10-07').error).toBeNull()
    })

    it('accepts exactly 31 days (inclusive) but not 32', () => {
        expect(resolveRange('2026-10-01', '2026-10-31').error).toBeNull()
        expect(resolveRange('2026-10-01', '2026-11-01').error).not.toBeNull()
    })

    it('rejects an end date before the start date', () => {
        const result = resolveRange('2026-10-07', '2026-10-01')
        expect(result.range).toEqual({})
        expect(result.error).not.toBeNull()
    })
})