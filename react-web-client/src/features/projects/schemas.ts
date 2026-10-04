import { z } from 'zod'

export const projectSchema = z.object({
    name: z.string().trim().min(1, 'Project name is required').max(255, 'Project name is too long'),
    description: z.string().trim(),
})

export type ProjectFormValues = z.infer<typeof projectSchema>