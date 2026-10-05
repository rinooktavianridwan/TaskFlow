import { describe, expect, it } from 'vitest'
import {
    forgotPasswordSchema,
    loginSchema,
    registerSchema,
    resetPasswordSchema,
    verifyRegistrationSchema,
} from './schemas'

describe('loginSchema', () => {
    it('accepts valid credentials', () => {
        expect(loginSchema.safeParse({ email: 'user@example.com', password: 'secret' }).success).toBe(true)
    })

    it('rejects an invalid email and an empty password', () => {
        expect(loginSchema.safeParse({ email: 'not-an-email', password: 'secret' }).success).toBe(false)
        expect(loginSchema.safeParse({ email: 'user@example.com', password: '' }).success).toBe(false)
    })
})

describe('registerSchema', () => {
    const valid = {
        name: 'Sam',
        email: 'sam@example.com',
        password: 'password123',
        password_confirmation: 'password123',
    }

    it('accepts valid input', () => {
        expect(registerSchema.safeParse(valid).success).toBe(true)
    })

    it('rejects a password shorter than 8 characters', () => {
        const result = registerSchema.safeParse({ ...valid, password: 'short', password_confirmation: 'short' })
        expect(result.success).toBe(false)
        if (!result.success) expect(result.error.issues[0].path).toEqual(['password'])
    })

    it('reports a mismatched confirmation on the confirmation field', () => {
        const result = registerSchema.safeParse({ ...valid, password_confirmation: 'different123' })
        expect(result.success).toBe(false)
        if (!result.success) expect(result.error.issues[0].path).toEqual(['password_confirmation'])
    })

    it('rejects a blank name', () => {
        expect(registerSchema.safeParse({ ...valid, name: '   ' }).success).toBe(false)
    })
})

describe('verifyRegistrationSchema', () => {
    it('accepts exactly 6 digits', () => {
        expect(verifyRegistrationSchema.safeParse({ otp_code: '123456' }).success).toBe(true)
    })

    it('rejects other lengths and non-digits', () => {
        expect(verifyRegistrationSchema.safeParse({ otp_code: '12345' }).success).toBe(false)
        expect(verifyRegistrationSchema.safeParse({ otp_code: '1234567' }).success).toBe(false)
        expect(verifyRegistrationSchema.safeParse({ otp_code: '12345a' }).success).toBe(false)
    })
})

describe('forgotPasswordSchema', () => {
    it('requires a valid email', () => {
        expect(forgotPasswordSchema.safeParse({ email: 'user@example.com' }).success).toBe(true)
        expect(forgotPasswordSchema.safeParse({ email: 'nope' }).success).toBe(false)
    })
})

describe('resetPasswordSchema', () => {
    it('accepts matching passwords of at least 8 characters', () => {
        expect(
            resetPasswordSchema.safeParse({ password: 'newpassword1', password_confirmation: 'newpassword1' }).success,
        ).toBe(true)
    })

    it('reports a mismatched confirmation on the confirmation field', () => {
        const result = resetPasswordSchema.safeParse({ password: 'newpassword1', password_confirmation: 'other-one-1' })
        expect(result.success).toBe(false)
        if (!result.success) expect(result.error.issues[0].path).toEqual(['password_confirmation'])
    })
})