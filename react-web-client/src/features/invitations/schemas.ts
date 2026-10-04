import { z } from 'zod'
import { PROJECT_ROLES } from '@/features/projects/roles'

export const invitationSchema = z.object({
    email: z.email('Enter a valid email address'),
    role: z.enum(PROJECT_ROLES),
})

export type InvitationFormValues = z.infer<typeof invitationSchema>