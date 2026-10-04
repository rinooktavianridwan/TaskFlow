import { isPast } from '@/lib/dates'
import type { Invitation, InvitationStatus } from './types'

export type InvitationDisplayStatus = InvitationStatus | 'expired'

export const INVITATION_STATUSES: InvitationStatus[] = ['pending', 'accepted', 'declined']

export const INVITATION_STATUS_LABELS: Record<InvitationDisplayStatus, string> = {
    pending: 'Pending',
    accepted: 'Accepted',
    declined: 'Declined',
    expired: 'Expired',
}

// Backend tidak mengubah status undangan yang kedaluwarsa: tetap "pending" dengan expires_at di masa lalu.
export function getDisplayStatus(invitation: Invitation): InvitationDisplayStatus {
    return invitation.status === 'pending' && isPast(invitation.expires_at) ? 'expired' : invitation.status
}