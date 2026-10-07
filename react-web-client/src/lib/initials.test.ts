import { describe, expect, it } from 'vitest'
import { getInitials } from './initials'

describe('getInitials', () => {
    it('uses the first letters of the first and last word', () => {
        expect(getInitials('Sam Altman')).toBe('SA')
        expect(getInitials('ada king lovelace')).toBe('AL')
    })

    it('uses a single letter for a single word', () => {
        expect(getInitials('sam')).toBe('S')
    })

    it('ignores extra whitespace', () => {
        expect(getInitials('  Sam   Lee ')).toBe('SL')
    })

    it('falls back to a question mark for a blank name', () => {
        expect(getInitials('   ')).toBe('?')
    })
})