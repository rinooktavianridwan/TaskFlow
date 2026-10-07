import { describe, expect, it } from 'vitest'
import {
    canChangeTaskStatus,
    canManageChecklist,
    canManageProject,
    canManageTasks,
    canToggleChecklistItem,
} from './permissions'

describe('canManageProject', () => {
    it('allows only owners', () => {
        expect(canManageProject('owner')).toBe(true)
        expect(canManageProject('editor')).toBe(false)
        expect(canManageProject('viewer')).toBe(false)
    })

    it('denies when the role is unknown', () => {
        expect(canManageProject(undefined)).toBe(false)
        expect(canManageProject(null)).toBe(false)
    })
})

describe('canManageTasks', () => {
    it('allows owners and editors', () => {
        expect(canManageTasks('owner')).toBe(true)
        expect(canManageTasks('editor')).toBe(true)
    })

    it('denies viewers and unknown roles', () => {
        expect(canManageTasks('viewer')).toBe(false)
        expect(canManageTasks(undefined)).toBe(false)
        expect(canManageTasks(null)).toBe(false)
    })
})

describe('canChangeTaskStatus', () => {
    it('lets owners and editors change any task', () => {
        expect(canChangeTaskStatus('owner', 99, 1)).toBe(true)
        expect(canChangeTaskStatus('editor', null, 1)).toBe(true)
    })

    it('lets a viewer change only the task assigned to them', () => {
        expect(canChangeTaskStatus('viewer', 1, 1)).toBe(true)
        expect(canChangeTaskStatus('viewer', 2, 1)).toBe(false)
        expect(canChangeTaskStatus('viewer', null, 1)).toBe(false)
    })

    it('denies a viewer when the current user is unknown', () => {
        expect(canChangeTaskStatus('viewer', 1, undefined)).toBe(false)
    })

    it('denies when the role is unknown', () => {
        expect(canChangeTaskStatus(undefined, 1, 1)).toBe(false)
        expect(canChangeTaskStatus(null, 1, 1)).toBe(false)
    })
})

describe('canManageChecklist', () => {
    it('allows owners and editors to add, rename and delete items', () => {
        expect(canManageChecklist('owner')).toBe(true)
        expect(canManageChecklist('editor')).toBe(true)
    })

    it('denies viewers (even the assignee) and unknown roles', () => {
        expect(canManageChecklist('viewer')).toBe(false)
        expect(canManageChecklist(undefined)).toBe(false)
        expect(canManageChecklist(null)).toBe(false)
    })
})

describe('canToggleChecklistItem', () => {
    it('lets owners and editors check items on any task', () => {
        expect(canToggleChecklistItem('owner', 99, 1)).toBe(true)
        expect(canToggleChecklistItem('editor', null, 1)).toBe(true)
    })

    it('lets a viewer check items only on the task assigned to them', () => {
        expect(canToggleChecklistItem('viewer', 1, 1)).toBe(true)
        expect(canToggleChecklistItem('viewer', 2, 1)).toBe(false)
        expect(canToggleChecklistItem('viewer', null, 1)).toBe(false)
    })

    it('denies when the role or the current user is unknown', () => {
        expect(canToggleChecklistItem(undefined, 1, 1)).toBe(false)
        expect(canToggleChecklistItem('viewer', 1, undefined)).toBe(false)
    })
})