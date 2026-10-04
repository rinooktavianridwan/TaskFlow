import { z } from 'zod'

export const loginSchema = z.object({
    email: z.email('Enter a valid email address'),
    password: z.string().min(1, 'Password is required'),
})

export type LoginFormValues = z.infer<typeof loginSchema>

export const registerSchema = z
    .object({
        name: z.string().trim().min(1, 'Name is required').max(255, 'Name is too long'),
        email: z.email('Enter a valid email address'),
        password: z.string().min(8, 'Password must be at least 8 characters'),
        password_confirmation: z.string().min(1, 'Confirm your password'),
    })
    .refine((values) => values.password === values.password_confirmation, {
        message: 'Passwords do not match',
        path: ['password_confirmation'],
    })

export type RegisterFormValues = z.infer<typeof registerSchema>

export const verifyRegistrationSchema = z.object({
    otp_code: z.string().regex(/^\d{6}$/, 'Enter the 6-digit code'),
})

export type VerifyRegistrationFormValues = z.infer<typeof verifyRegistrationSchema>