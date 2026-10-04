import type { ProjectRole } from './types'

// Owner: ubah/hapus project, kelola member dan undangan (sama dengan ProjectPolicy::update di backend).
// Aturan ini hanya untuk menyembunyikan tombol; yang menegakkan tetap backend (403).
export function canManageProject(role: ProjectRole | null | undefined): boolean {
    return role === 'owner'
}

// Owner dan editor: buat, ubah semua field, dan hapus task (ProjectPolicy::createTask, TaskPolicy::delete).
export function canManageTasks(role: ProjectRole | null | undefined): boolean {
    return role === 'owner' || role === 'editor'
}

// Viewer hanya boleh mengubah status task yang ditugaskan kepadanya (TaskService::ensureCanUpdate).
export function canChangeTaskStatus(
    role: ProjectRole | null | undefined,
    assignedTo: number | null,
    userId: number | undefined,
): boolean {
    if (canManageTasks(role)) return true
    return role === 'viewer' && userId !== undefined && assignedTo === userId
}   