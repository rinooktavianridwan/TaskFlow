import type { ProjectRole } from './types'

// Owner: ubah/hapus project, kelola member dan undangan.
export function canManageProject(role: ProjectRole | null | undefined): boolean {
    return role === 'owner'
}

// Owner dan editor: buat, ubah semua field, dan hapus task.
export function canManageTasks(role: ProjectRole | null | undefined): boolean {
    return role === 'owner' || role === 'editor'
}

// Viewer hanya boleh mengubah status task yang ditugaskan kepadanya.
export function canChangeTaskStatus(
    role: ProjectRole | null | undefined,
    assignedTo: number | null,
    userId: number | undefined,
): boolean {
    if (canManageTasks(role)) return true
    return role === 'viewer' && userId !== undefined && assignedTo === userId
}

// Tambah item, ganti judul, dan hapus item checklist: owner dan editor saja.
export function canManageChecklist(role: ProjectRole | null | undefined): boolean {
    return canManageTasks(role)
}

// Mencentang item: owner/editor, atau viewer yang DITUGASKAN pada task itu (aturannya sama dengan status).
export function canToggleChecklistItem(
    role: ProjectRole | null | undefined,
    assignedTo: number | null,
    userId: number | undefined,
): boolean {
    return canChangeTaskStatus(role, assignedTo, userId)
}