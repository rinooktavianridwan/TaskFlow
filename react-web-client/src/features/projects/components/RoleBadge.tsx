import { PROJECT_ROLE_LABELS } from '../roles'
import type { ProjectRole } from '../types'

const styles: Record<ProjectRole, string> = {
    owner: 'bg-blue-50 text-blue-700',
    editor: 'bg-amber-50 text-amber-700',
    viewer: 'bg-gray-100 text-gray-600',
}

export function RoleBadge({ role }: { role?: ProjectRole | null }) {
    if (!role) return null

    return (
        <span className={`shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold ${styles[role]}`}>
            {PROJECT_ROLE_LABELS[role]}
        </span>
    )
}