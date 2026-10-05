import { useState } from 'react'
import { EmptyState, ErrorState, LoadingState } from '@/components/feedback/states'
import { useToast } from '@/components/feedback/toast-context'
import { Button } from '@/components/ui/Button'
import { Pagination } from '@/components/ui/Pagination'
import { getErrorMessage } from '@/lib/form-errors'
import { parsePage, toPageParam, useUrlParams } from '@/lib/use-url-params'
import { ReceivedInvitationRow } from '../components/ReceivedInvitationRow'
import { useAcceptInvitation, useDeclineInvitation, useReceivedInvitations } from '../queries'
import type { ReceivedInvitation } from '../types'

export function ReceivedInvitationsPage() {
    const [params, updateParams] = useUrlParams()
    const page = parsePage(params.get('page'))
    const { showToast } = useToast()
    const [actionError, setActionError] = useState<string | null>(null)

    const { data, isPending, isError, error, refetch, isPlaceholderData } = useReceivedInvitations({ page })
    const accept = useAcceptInvitation()
    const decline = useDeclineInvitation()

    const busy = accept.isPending || decline.isPending
    const activeToken = accept.isPending ? accept.variables : decline.isPending ? decline.variables : undefined

    async function respond(invitation: ReceivedInvitation, action: 'accept' | 'decline') {
        setActionError(null)
        try {
            if (action === 'accept') {
                await accept.mutateAsync(invitation.token)
                showToast(`You joined ${invitation.project_name}.`)
            } else {
                await decline.mutateAsync(invitation.token)
                showToast(`Invitation to ${invitation.project_name} declined.`)
            }
        } catch (error) {
            // Mis. kedaluwarsa atau sudah menjadi member di waktu bersamaan: ambil ulang agar daftar tidak basi.
            setActionError(getErrorMessage(error))
            void refetch()
        }
    }

    function renderContent() {
        if (isPending) return <LoadingState />
        if (isError) return <ErrorState message={getErrorMessage(error)} onRetry={() => void refetch()} />

        if (data.items.length === 0) {
            // Halaman terakhir bisa kosong setelah undangan di dalamnya diproses.
            if (page > 1) {
                return (
                    <EmptyState
                        title="Nothing on this page"
                        action={
                            <Button type="button" fullWidth={false} onClick={() => updateParams({ page: null })}>
                                Go to first page
                            </Button>
                        }
                    />
                )
            }
            return (
                <EmptyState
                    title="No pending invitations"
                    description="When someone invites you to a project, it will appear here."
                />
            )
        }

        return (
            <div className={isPlaceholderData ? 'opacity-60 transition-opacity' : 'transition-opacity'}>
                <ul className="space-y-3">
                    {data.items.map((invitation) => (
                        <ReceivedInvitationRow
                            key={invitation.token}
                            invitation={invitation}
                            disabled={busy}
                            accepting={accept.isPending && activeToken === invitation.token}
                            declining={decline.isPending && activeToken === invitation.token}
                            onAccept={(target) => void respond(target, 'accept')}
                            onDecline={(target) => void respond(target, 'decline')}
                        />
                    ))}
                </ul>
                <Pagination meta={data.meta} onPageChange={(next) => updateParams({ page: toPageParam(next) })} />
            </div>
        )
    }

    return (
        <div>
            <h1 className="text-2xl font-bold text-gray-800">Invitations</h1>
            <p className="mt-1 text-sm text-gray-600">Projects you have been invited to join.</p>

            {actionError && (
                <p role="alert" className="mt-4 rounded-2xl bg-red-50 px-3 py-2 text-sm text-red-600">
                    {actionError}
                </p>
            )}

            <div className="mt-6">{renderContent()}</div>
        </div>
    )
}