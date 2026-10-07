import { describe, expect, it } from 'vitest'
import { getTimezoneOptions } from './timezones'

describe('getTimezoneOptions', () => {
    it('always includes UTC and the current value', () => {
        const options = getTimezoneOptions('Mars/Base')
        expect(options).toContain('UTC')
        expect(options).toContain('Mars/Base')
    })

    it('includes common IANA zones', () => {
        expect(getTimezoneOptions('UTC')).toContain('Asia/Jakarta')
    })

    it('has no duplicates and is sorted', () => {
        const options = getTimezoneOptions('Asia/Jakarta')
        expect(new Set(options).size).toBe(options.length)
        expect(options).toEqual([...options].sort((a, b) => a.localeCompare(b)))
    })
})