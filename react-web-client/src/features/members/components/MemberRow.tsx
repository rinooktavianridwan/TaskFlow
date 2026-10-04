import { useId } from 'react'
import { RoleBadge } from '@/features/projects/components/RoleBadge'
import { PROJECT_ROLE_LABELS, PROJECT_ROLES } from '@/features/projects/roles'
import type { ProjectRole } from '@/features/projects/types'
import type { Member } from '../types'

type MemberRowProps = {
    member: Member
    isSelf: boolean
    canManage: boolean
    busy: boolean
    onChangeRole: (member: Member, role: ProjectRole) => void
    onRemove: (member: Member) => void
}

export function MemberRow({ member, isSelf, canManage, busy, onChangeRole, onRemove }: MemberRowProps) {
    const selectId = useId()

    return (
        <li className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white p-4">
            <div className="min-w-0">
                <p className="font-semibold break-words text-gray-800">
                    {member.name}
                    {isSelf && <span className="ml-2 text-xs font-medium text-gray-400">(you)</span>}
                </p>
                <p className="text-sm break-words text-gray-500">{member.email}</p>
            </div>

            <div className="flex flex-wrap items-center gap-3">
                {canManage ? (
                    <div>
                        <label htmlFor={selectId} className="sr-only">
                            Role for {member.name}
                        </label>
                        <select
                            id={selectId}
                            value={member.role}
                            disabled={busy}
                            onChange={(event) => onChangeRole(member, event.target.value as ProjectRole)}
                            className="rounded-full border border-gray-200 bg-white px-3 py-1 text-sm font-medium text-gray-700 outline-none focus:border-blue-500 disabled:opacity-60"
                        >
                            {PROJECT_ROLES.map((role) => (
                                <option key={role} value={role}>
                                    {PROJECT_ROLE_LABELS[role]}
                                </option>
                            ))}
                        </select>
                    </div>
                ) : (
                    <RoleBadge role={member.role} />
                )}

                {/* Siapa pun boleh keluar sendiri; hanya owner yang boleh mengeluarkan orang lain. */}
                {isSelf ? (
                    <button
                        type="button"
                        onClick={() => onRemove(member)}
                        className="rounded-xl border border-gray-200 px-3 py-1 text-sm font-medium text-gray-700 hover:bg-gray-100"
                    >
                        Leave
                    </button>
                ) : (
                    canManage && (
                        <button
                            type="button"
                            onClick={() => onRemove(member)}
                            className="rounded-xl border border-red-200 px-3 py-1 text-sm font-medium text-red-600 hover:bg-red-50"
                        >
                            Remove
                        </button>
                    )
                )}
            </div>
        </li>
    )
}