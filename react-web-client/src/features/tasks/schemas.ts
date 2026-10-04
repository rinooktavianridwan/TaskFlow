import { z } from 'zod'

export const taskSchema = z.object({
    title: z.string().trim().min(1, 'Title is required').max(255, 'Title is too long'),
    description: z.string().trim(),
    assigned_to: z.string(),
    due_date: z.string(),
})

export type TaskFormValues = z.infer<typeof taskSchema>