// Semua nilai `action` yang dikenal (timeline project, riwayat task, rangkuman harian).
export const PROJECT_ACTIVITY_ACTIONS = [
    'created',
    'updated',
    'status_changed',
    'assigned',
    'task_deleted',
    'checklist_item_added',
    'checklist_item_completed',
    'checklist_item_reopened',
    'checklist_item_removed',
    'project_updated',
    'invitation_sent',
    'invitation_revoked',
    'invitation_accepted',
    'invitation_declined',
    'member_role_changed',
    'member_removed',
    'member_left',
] as const

export type KnownActivityAction = (typeof PROJECT_ACTIVITY_ACTIONS)[number]

export type ActivityTone = 'default' | 'success' | 'danger'

const ACTION_LABELS: Record<KnownActivityAction, string> = {
    created: 'Task created',
    updated: 'Task updated',
    status_changed: 'Status changed',
    assigned: 'Assignment changed',
    task_deleted: 'Task deleted',
    checklist_item_added: 'Checklist item added',
    checklist_item_completed: 'Checklist item completed',
    checklist_item_reopened: 'Checklist item reopened',
    checklist_item_removed: 'Checklist item removed',
    project_updated: 'Project updated',
    invitation_sent: 'Invitation sent',
    invitation_revoked: 'Invitation revoked',
    invitation_accepted: 'Invitation accepted',
    invitation_declined: 'Invitation declined',
    member_role_changed: 'Member role changed',
    member_removed: 'Member removed',
    member_left: 'Member left',
}

const ACTION_TONES: Partial<Record<KnownActivityAction, ActivityTone>> = {
    checklist_item_completed: 'success',
    invitation_accepted: 'success',
    task_deleted: 'danger',
    checklist_item_removed: 'danger',
    invitation_revoked: 'danger',
    invitation_declined: 'danger',
    member_removed: 'danger',
    member_left: 'danger',
}

function isKnownAction(action: string): action is KnownActivityAction {
    return (PROJECT_ACTIVITY_ACTIONS as readonly string[]).includes(action)
}

// Action yang tidak dikenal (mis. ditambah backend belakangan) tidak boleh membuat UI error.
export function getActionLabel(action: string): string {
    if (isKnownAction(action)) return ACTION_LABELS[action]
    const text = action.replaceAll('_', ' ').trim()
    return text === '' ? 'Activity' : text.charAt(0).toUpperCase() + text.slice(1)
}

export function getActionTone(action: string): ActivityTone {
    return isKnownAction(action) ? (ACTION_TONES[action] ?? 'default') : 'default'
}

// Membaca satu nilai string dari `metadata` (bentuknya tidak dijamin).
export function getMetadataString(metadata: Record<string, unknown> | null, key: string): string | null {
    const value = metadata?.[key]
    return typeof value === 'string' && value !== '' ? value : null
}