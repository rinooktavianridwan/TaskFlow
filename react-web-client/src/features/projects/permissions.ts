import type { ProjectRole } from './types'

// Owner : ubah/hapus project, kelola member dan undangan.
export function canManageProject(role: ProjectRole | null | undefined): boolean {
    return role === 'owner'
}

// Owner dan editor: buat, ubah semua field, dan hapus task (ProjectPolicy::createTask, TaskPolicy::delete).
export function canManageTasks(role: ProjectRole | null | undefined): boolean {
    return role === 'owner' || role === 'editor'
}