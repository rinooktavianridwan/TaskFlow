import { describe, expect, it } from 'vitest'
import { changePasswordSchema, profileSchema } from './schemas'

describe('profileSchema', () => {
    it('accepts a name and a timezone', () => {
        expect(profileSchema.safeParse({ name: 'Sam', timezone: 'Asia/Jakarta' }).success).toBe(true)
    })

    it('trims the name and rejects a blank one', () => {
        const trimmed = profileSchema.safeParse({ name: '  Sam  ', timezone: 'UTC' })
        expect(trimmed.success && trimmed.data.name).toBe('Sam')
        expect(profileSchema.safeParse({ name: '   ', timezone: 'UTC' }).success).toBe(false)
    })

    it('rejects a name longer than 255 characters and an empty timezone', () => {
        expect(profileSchema.safeParse({ name: 'a'.repeat(256), timezone: 'UTC' }).success).toBe(false)
        expect(profileSchema.safeParse({ name: 'Sam', timezone: '' }).success).toBe(false)
    })
})

describe('changePasswordSchema', () => {
    const valid = {
        current_password: 'oldpassword1',
        password: 'newpassword1',
        password_confirmation: 'newpassword1',
    }

    it('accepts valid input', () => {
        expect(changePasswordSchema.safeParse(valid).success).toBe(true)
    })

    it('rejects a new password shorter than 8 characters', () => {
        const result = changePasswordSchema.safeParse({ ...valid, password: 'short', password_confirmation: 'short' })
        expect(result.success).toBe(false)
        if (!result.success) expect(result.error.issues[0].path).toEqual(['password'])
    })

    it('reports a mismatched confirmation on the confirmation field', () => {
        const result = changePasswordSchema.safeParse({ ...valid, password_confirmation: 'different123' })
        expect(result.success).toBe(false)
        if (!result.success) expect(result.error.issues[0].path).toEqual(['password_confirmation'])
    })

    it('rejects a new password equal to the current one', () => {
        const result = changePasswordSchema.safeParse({
            current_password: 'samepassword1',
            password: 'samepassword1',
            password_confirmation: 'samepassword1',
        })
        expect(result.success).toBe(false)
        if (!result.success) expect(result.error.issues[0].path).toEqual(['password'])
    })

    it('requires the current password', () => {
        expect(changePasswordSchema.safeParse({ ...valid, current_password: '' }).success).toBe(false)
    })
})