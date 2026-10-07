import { z } from 'zod'

export const profileSchema = z.object({
    name: z.string().trim().min(1, 'Name is required').max(255, 'Name is too long'),
    timezone: z.string().min(1, 'Choose a timezone'),
})

export type ProfileFormValues = z.infer<typeof profileSchema>

export const changePasswordSchema = z
    .object({
        current_password: z.string().min(1, 'Current password is required'),
        password: z.string().min(8, 'Password must be at least 8 characters'),
        password_confirmation: z.string().min(1, 'Confirm your new password'),
    })
    .refine((values) => values.password === values.password_confirmation, {
        message: 'Passwords do not match',
        path: ['password_confirmation'],
    })
    // Backend memakai aturan `different:current_password`.
    .refine((values) => values.password !== values.current_password, {
        message: 'New password must be different from the current one',
        path: ['password'],
    })

export type ChangePasswordFormValues = z.infer<typeof changePasswordSchema>