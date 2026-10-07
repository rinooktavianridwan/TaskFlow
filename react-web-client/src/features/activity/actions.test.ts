import { describe, expect, it } from 'vitest'
import { getActionLabel, getActionTone, getMetadataString, PROJECT_ACTIVITY_ACTIONS } from './actions'

describe('getActionLabel', () => {
    it('has a readable label for every known action', () => {
        for (const action of PROJECT_ACTIVITY_ACTIONS) {
            const label = getActionLabel(action)
            expect(label).not.toBe('')
            expect(label).not.toContain('_')
        }
    })

    it('maps a known action to its label', () => {
        expect(getActionLabel('status_changed')).toBe('Status changed')
    })

    it('humanizes an unknown action instead of failing', () => {
        expect(getActionLabel('some_new_action')).toBe('Some new action')
        expect(getActionLabel('')).toBe('Activity')
    })
})

describe('getActionTone', () => {
    it('marks completions as success and removals as danger', () => {
        expect(getActionTone('checklist_item_completed')).toBe('success')
        expect(getActionTone('member_removed')).toBe('danger')
    })

    it('uses the default tone for everything else, including unknown actions', () => {
        expect(getActionTone('created')).toBe('default')
        expect(getActionTone('something_unknown')).toBe('default')
    })
})

describe('getMetadataString', () => {
    it('returns a non-empty string value', () => {
        expect(getMetadataString({ task_title: 'Write docs' }, 'task_title')).toBe('Write docs')
    })

    it('returns null for missing, empty or non-string values', () => {
        expect(getMetadataString(null, 'task_title')).toBeNull()
        expect(getMetadataString({}, 'task_title')).toBeNull()
        expect(getMetadataString({ task_title: '' }, 'task_title')).toBeNull()
        expect(getMetadataString({ task_title: 5 }, 'task_title')).toBeNull()
    })
})