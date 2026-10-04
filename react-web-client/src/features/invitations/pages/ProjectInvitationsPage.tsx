import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { EmptyState, ErrorState, LoadingState } from '@/components/feedback/states'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { Pagination } from '@/components/ui/Pagination'
import { canManageProject } from '@/features/projects/permissions'
import { useProject } from '@/features/projects/queries'
import { getErrorMessage } from '@/lib/form-errors'
import { InvitationFormModal } from '../components/InvitationFormModal'
import { InvitationRow } from '../components/InvitationRow'
import { useInvitations, useRevokeInvitation } from '../queries'
import { INVITATION_STATUS_LABELS, INVITATION_STATUSES } from '../status'
import type { Invitation, InvitationStatus } from '../types'

type StatusFilter = InvitationStatus | 'all'

const statusFilters: { value: StatusFilter; label: string }[] = [
    { value: 'all', label: 'All' },
    ...INVITATION_STATUSES.map((status) => ({ value: status, label: INVITATION_STATUS_LABELS[status] })),
]

export function ProjectInvitationsPage() {
    const { projectId } = useParams()
    const id = Number(projectId)
    const { data: project } = useProject(id)

    // Hanya owner yang boleh mengelola undangan (backend membalas 403 untuk yang lain).
    // Dicek di wrapper agar query undangan tidak pernah ditembakkan oleh non-owner.
    if (!canManageProject(project?.role)) {
        return <ErrorState title="No access" message="Only project owners can manage invitations." />
    }

    return <InvitationsContent projectId={id} />
}

function InvitationsContent({ projectId }: { projectId: number }) {
    const [status, setStatus] = useState<StatusFilter>('all')
    const [page, setPage] = useState(1)
    const [inviting, setInviting] = useState(false)
    const [pendingRevoke, setPendingRevoke] = useState<Invitation | null>(null)
    const [revokeError, setRevokeError] = useState<string | null>(null)

    const { data, isPending, isError, error, refetch, isPlaceholderData } = useInvitations(projectId, {
        page,
        status: status === 'all' ? undefined : status,
    })
    const revokeInvitation = useRevokeInvitation(projectId)

    async function handleRevoke(invitation: Invitation) {
        setRevokeError(null)
        try {
            await revokeInvitation.mutateAsync(invitation.id)
            setPendingRevoke(null)
        } catch (error) {
            // Mis. "Only pending invitations can be revoked." bila sudah diterima di waktu bersamaan.
            setRevokeError(getErrorMessage(error))
        }
    }

    function openRevoke(invitation: Invitation) {
        setRevokeError(null)
        setPendingRevoke(invitation)
    }

    function closeRevoke() {
        setPendingRevoke(null)
        setRevokeError(null)
    }

    function renderContent() {
        if (isPending) return <LoadingState />
        if (isError) return <ErrorState message={getErrorMessage(error)} onRetry={() => void refetch()} />

        if (data.items.length === 0) {
            if (status !== 'all') {
                return <EmptyState title="No invitations found" description="No invitations match this filter." />
            }
            return (
                <EmptyState
                    title="No invitations yet"
                    description="Invite people by email to collaborate on this project."
                    action={
                        <Button type="button" fullWidth={false} onClick={() => setInviting(true)}>
                            Invite member
                        </Button>
                    }
                />
            )
        }

        return (
            <div className={isPlaceholderData ? 'opacity-60 transition-opacity' : 'transition-opacity'}>
                <ul className="space-y-3">
                    {data.items.map((invitation) => (
                        <InvitationRow key={invitation.id} invitation={invitation} onRevoke={openRevoke} />
                    ))}
                </ul>
                <Pagination meta={data.meta} onPageChange={setPage} />
            </div>
        )
    }

    return (
        <div>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div role="group" aria-label="Filter by status" className="flex flex-wrap gap-2">
                    {statusFilters.map((filter) => (
                        <button
                            key={filter.value}
                            type="button"
                            aria-pressed={status === filter.value}
                            onClick={() => {
                                setStatus(filter.value)
                                setPage(1)
                            }}
                            className={`rounded-full px-3 py-1 text-sm font-medium transition ${status === filter.value
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50'
                                }`}
                        >
                            {filter.label}
                        </button>
                    ))}
                </div>

                <Button type="button" fullWidth={false} onClick={() => setInviting(true)}>
                    Invite member
                </Button>
            </div>

            <div className="mt-6">{renderContent()}</div>

            <InvitationFormModal open={inviting} onClose={() => setInviting(false)} projectId={projectId} />

            <ConfirmDialog
                open={pendingRevoke !== null}
                title="Revoke invitation"
                message={`The invitation for ${pendingRevoke?.email ?? 'this address'} can no longer be accepted.`}
                confirmLabel="Revoke"
                loading={revokeInvitation.isPending}
                error={revokeError}
                onConfirm={() => {
                    if (pendingRevoke) void handleRevoke(pendingRevoke)
                }}
                onCancel={closeRevoke}
            />
        </div>
    )
}