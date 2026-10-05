import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { getSafeRedirect, withRedirect } from './redirect'

beforeEach(() => {
    // Test berjalan di Node: sediakan hanya bagian window yang dipakai getSafeRedirect.
    vi.stubGlobal('window', { location: { origin: 'http://localhost:3000' } })
})

afterEach(() => {
    vi.unstubAllGlobals()
})

describe('getSafeRedirect', () => {
    it('accepts internal paths, keeping query and hash', () => {
        expect(getSafeRedirect('/projects')).toBe('/projects')
        expect(getSafeRedirect('/projects/1/tasks?page=2#top')).toBe('/projects/1/tasks?page=2#top')
    })

    it('rejects empty values', () => {
        expect(getSafeRedirect(null)).toBeNull()
        expect(getSafeRedirect('')).toBeNull()
    })

    it('rejects anything that is not an absolute internal path', () => {
        expect(getSafeRedirect('projects')).toBeNull()
        expect(getSafeRedirect('https://evil.com')).toBeNull()
        expect(getSafeRedirect('javascript:alert(1)')).toBeNull()
    })

    it('rejects protocol-relative and browser-normalized external URLs', () => {
        expect(getSafeRedirect('//evil.com')).toBeNull()
        expect(getSafeRedirect('/\\evil.com')).toBeNull()
        expect(getSafeRedirect('/\t/evil.com')).toBeNull()
    })
})

describe('withRedirect', () => {
    it('returns the bare path when there is nothing to add', () => {
        expect(withRedirect('/register', null)).toBe('/register')
    })

    it('encodes the redirect target', () => {
        expect(withRedirect('/login', '/invitations/abc?x=1')).toBe('/login?redirect=%2Finvitations%2Fabc%3Fx%3D1')
    })

    it('combines extra params with the redirect', () => {
        expect(withRedirect('/register/verify', '/invitations/abc', { email: 'a+b@x.com' })).toBe(
            '/register/verify?email=a%2Bb%40x.com&redirect=%2Finvitations%2Fabc',
        )
    })
})