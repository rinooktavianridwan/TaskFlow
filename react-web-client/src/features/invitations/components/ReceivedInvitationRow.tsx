import { Button } from '@/components/ui/Button'
import { RoleBadge } from '@/features/projects/components/RoleBadge'
import { formatDate } from '@/lib/dates'
import type { ReceivedInvitation } from '../types'

type ReceivedInvitationRowProps = {
    invitation: ReceivedInvitation
    // Ada aksi yang sedang berjalan (di baris mana pun): semua tombol dinonaktifkan.
    disabled: boolean
    accepting: boolean
    declining: boolean
    onAccept: (invitation: ReceivedInvitation) => void
    onDecline: (invitation: ReceivedInvitation) => void
}

export function ReceivedInvitationRow({
    invitation,
    disabled,
    accepting,
    declining,
    onAccept,
    onDecline,
}: ReceivedInvitationRowProps) {
    return (
        <li className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white p-4">
            <div className="min-w-0">
                <p className="font-semibold break-words text-gray-800">{invitation.project_name}</p>
                <p className="text-xs text-gray-500">
                    Received {formatDate(invitation.created_at)} · Expires {formatDate(invitation.expires_at)}
                </p>
            </div>

            <div className="flex flex-wrap items-center gap-3">
                <RoleBadge role={invitation.role} />
                <Button
                    type="button"
                    variant="secondary"
                    fullWidth={false}
                    disabled={disabled}
                    loading={declining}
                    onClick={() => onDecline(invitation)}
                >
                    Decline
                </Button>
                <Button
                    type="button"
                    fullWidth={false}
                    disabled={disabled}
                    loading={accepting}
                    onClick={() => onAccept(invitation)}
                >
                    Accept
                </Button>
            </div>
        </li>
    )
}