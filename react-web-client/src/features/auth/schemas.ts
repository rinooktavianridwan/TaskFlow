import { z } from 'zod'

const newPassword = z.string().min(8, 'Password must be at least 8 characters')
const passwordConfirmation = z.string().min(1, 'Confirm your password')

function passwordsMatch(values: { password: string; password_confirmation: string }): boolean {
    return values.password === values.password_confirmation
}

const passwordMismatch = { message: 'Passwords do not match', path: ['password_confirmation'] }

export const loginSchema = z.object({
    email: z.email('Enter a valid email address'),
    password: z.string().min(1, 'Password is required'),
})

export type LoginFormValues = z.infer<typeof loginSchema>

export const registerSchema = z
    .object({
        name: z.string().trim().min(1, 'Name is required').max(255, 'Name is too long'),
        email: z.email('Enter a valid email address'),
        password: newPassword,
        password_confirmation: passwordConfirmation,
    })
    .refine(passwordsMatch, passwordMismatch)

export type RegisterFormValues = z.infer<typeof registerSchema>

export const verifyRegistrationSchema = z.object({
    otp_code: z.string().regex(/^\d{6}$/, 'Enter the 6-digit code'),
})

export type VerifyRegistrationFormValues = z.infer<typeof verifyRegistrationSchema>

export const forgotPasswordSchema = z.object({
    email: z.email('Enter a valid email address'),
})

export type ForgotPasswordFormValues = z.infer<typeof forgotPasswordSchema>

// Token dan email datang dari tautan di email, jadi form hanya berisi password baru.
export const resetPasswordSchema = z
    .object({
        password: newPassword,
        password_confirmation: passwordConfirmation,
    })
    .refine(passwordsMatch, passwordMismatch)

export type ResetPasswordFormValues = z.infer<typeof resetPasswordSchema>