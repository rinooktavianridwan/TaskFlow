import { RoleBadge } from '@/features/projects/components/RoleBadge'
import { formatDate } from '@/lib/dates'
import { getDisplayStatus, INVITATION_STATUS_LABELS, type InvitationDisplayStatus } from '../status'
import type { Invitation } from '../types'

const badgeStyles: Record<InvitationDisplayStatus, string> = {
    pending: 'bg-amber-50 text-amber-700',
    accepted: 'bg-green-50 text-green-700',
    declined: 'bg-gray-100 text-gray-600',
    expired: 'bg-red-50 text-red-600',
}

type InvitationRowProps = {
    invitation: Invitation
    onRevoke: (invitation: Invitation) => void
}

export function InvitationRow({ invitation, onRevoke }: InvitationRowProps) {
    const displayStatus = getDisplayStatus(invitation)
    const showExpiry = displayStatus === 'pending' || displayStatus === 'expired'

    return (
        <li className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white p-4">
            <div className="min-w-0">
                <p className="font-semibold break-words text-gray-800">{invitation.email}</p>
                <p className="text-xs text-gray-500">
                    Sent {formatDate(invitation.created_at)}
                    {showExpiry &&
                        ` · ${displayStatus === 'expired' ? 'Expired' : 'Expires'} ${formatDate(invitation.expires_at)}`}
                </p>
            </div>

            <div className="flex flex-wrap items-center gap-3">
                <RoleBadge role={invitation.role} />
                <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${badgeStyles[displayStatus]}`}>
                    {INVITATION_STATUS_LABELS[displayStatus]}
                </span>
                {/* Backend hanya mengizinkan pembatalan undangan berstatus pending (termasuk yang kedaluwarsa). */}
                {invitation.status === 'pending' && (
                    <button
                        type="button"
                        onClick={() => onRevoke(invitation)}
                        className="rounded-xl border border-red-200 px-3 py-1 text-sm font-medium text-red-600 hover:bg-red-50"
                    >
                        Revoke
                    </button>
                )}
            </div>
        </li>
    )
}