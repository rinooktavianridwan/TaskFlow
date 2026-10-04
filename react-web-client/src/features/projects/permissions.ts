import type { ProjectRole } from './types'

// Owner saja: ubah/hapus project, kelola member dan undangan (sama dengan ProjectPolicy::update di backend).
export function canManageProject(role: ProjectRole | null | undefined): boolean {
    return role === 'owner'
}