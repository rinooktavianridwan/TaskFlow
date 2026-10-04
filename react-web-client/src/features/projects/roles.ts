import type { ProjectRole } from './types'

export const PROJECT_ROLES = ['owner', 'editor', 'viewer'] as const satisfies readonly ProjectRole[]

export const PROJECT_ROLE_LABELS: Record<ProjectRole, string> = {
    owner: 'Owner',
    editor: 'Editor',
    viewer: 'Viewer',
}