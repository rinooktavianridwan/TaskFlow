import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { getDisplayStatus } from './status'

beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-10-05T00:00:00Z'))
})

afterEach(() => {
    vi.useRealTimers()
})

describe('getDisplayStatus', () => {
    it('keeps a pending invitation pending before it expires', () => {
        expect(getDisplayStatus({ status: 'pending', expires_at: '2026-10-10T00:00:00Z' })).toBe('pending')
    })

    it('derives "expired" from a pending invitation whose expiry has passed', () => {
        expect(getDisplayStatus({ status: 'pending', expires_at: '2026-10-01T00:00:00Z' })).toBe('expired')
    })

    it('never overrides a processed invitation, even past its expiry', () => {
        expect(getDisplayStatus({ status: 'accepted', expires_at: '2026-10-01T00:00:00Z' })).toBe('accepted')
        expect(getDisplayStatus({ status: 'declined', expires_at: '2026-10-01T00:00:00Z' })).toBe('declined')
    })
})