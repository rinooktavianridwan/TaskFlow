import { describe, expect, it } from 'vitest'
import { diffTask } from './diff'
import type { Task, TaskPayload } from './types'

const task: Task = {
    id: 1,
    project_id: 1,
    title: 'Write docs',
    description: 'Initial draft',
    status: 'todo',
    assigned_to: 2,
    assignee: { id: 2, name: 'Sam', email: 'sam@example.com' },
    due_date: '2026-10-10T09:00:00Z',
    created_at: '2026-10-01T00:00:00Z',
    updated_at: '2026-10-01T00:00:00Z',
}

const unchanged: TaskPayload = {
    title: task.title,
    description: task.description,
    assigned_to: task.assigned_to,
    due_date: task.due_date,
}

describe('diffTask', () => {
    it('returns nothing when no field changed', () => {
        expect(diffTask(task, unchanged)).toEqual({})
    })

    it('returns only the fields that changed', () => {
        expect(diffTask(task, { ...unchanged, title: 'Write better docs' })).toEqual({ title: 'Write better docs' })
        expect(diffTask(task, { ...unchanged, assigned_to: 3 })).toEqual({ assigned_to: 3 })
    })

    it('sends null when a value is cleared', () => {
        expect(diffTask(task, { ...unchanged, description: null })).toEqual({ description: null })
        expect(diffTask(task, { ...unchanged, assigned_to: null })).toEqual({ assigned_to: null })
        expect(diffTask(task, { ...unchanged, due_date: null })).toEqual({ due_date: null })
    })

    it('treats the same instant in a different ISO format as unchanged', () => {
        expect(diffTask(task, { ...unchanged, due_date: '2026-10-10T09:00:00.000Z' })).toEqual({})
        expect(diffTask(task, { ...unchanged, due_date: '2026-10-10T16:00:00+07:00' })).toEqual({})
    })

    it('detects a due date set on a task that had none', () => {
        const noDueDate: Task = { ...task, due_date: null }
        expect(diffTask(noDueDate, { ...unchanged, due_date: null })).toEqual({})
        expect(diffTask(noDueDate, { ...unchanged, due_date: '2026-10-12T09:00:00Z' })).toEqual({
            due_date: '2026-10-12T09:00:00Z',
        })
    })

    it('never includes status', () => {
        expect(diffTask(task, { ...unchanged, title: 'New title' })).not.toHaveProperty('status')
    })
})