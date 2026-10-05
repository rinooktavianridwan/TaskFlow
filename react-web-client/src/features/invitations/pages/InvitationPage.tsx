import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { ErrorState, LoadingState } from '@/components/feedback/states'
import { useToast } from '@/components/feedback/toast-context'
import { Button } from '@/components/ui/Button'
import { useAuth } from '@/features/auth/auth-context'
import { RoleBadge } from '@/features/projects/components/RoleBadge'
import { formatDate } from '@/lib/dates'
import { getErrorMessage, getErrorStatus } from '@/lib/form-errors'
import { withRedirect } from '@/lib/redirect'
import { useAcceptInvitation, useDeclineInvitation, useInvitationPreview } from '../queries'
import { getDisplayStatus } from '../status'
import type { InvitationPreview } from '../types'

const primaryLinkClass =
    'block w-full rounded-2xl bg-blue-600 px-4 py-2 text-center font-semibold text-white transition hover:bg-blue-700'
const secondaryLinkClass =
    'block w-full rounded-2xl border border-gray-200 bg-white px-4 py-2 text-center font-semibold text-gray-700 transition hover:bg-gray-50'

const statusMessages = {
    accepted: 'This invitation has already been accepted.',
    declined: 'This invitation was declined.',
    expired: 'This invitation has expired. Ask the project owner to send you a new one.',
} as const

function describeActionError(error: unknown): string {
    switch (getErrorStatus(error)) {
        case 403:
            return 'This invitation was sent to a different email address. Log out and sign in with the invited address.'
        case 404:
            return 'This invitation no longer exists.'
        default:
            // 422 (kedaluwarsa / tidak pending / sudah member) dan 429.
            return getErrorMessage(error)
    }
}

export function InvitationPage() {
    const { token = '' } = useParams()
    const { isLoading: authLoading } = useAuth()
    const { data, isPending, isError, error, refetch } = useInvitationPreview(token)

    // Tunggu status login agar tombol Login/Register tidak sempat berkedip untuk user yang sudah masuk.
    if (authLoading || isPending) return <LoadingState />

    if (isError) {
        const status = getErrorStatus(error)
        if (status === 404) {
            return (
                <ErrorState
                    title="Invitation not found"
                    message="This link is invalid. Ask the project owner to send you a new invitation."
                />
            )
        }
        if (status === 429) {
            return (
                <ErrorState
                    title="Too many requests"
                    message="Please wait a moment, then try again."
                    onRetry={() => void refetch()}
                />
            )
        }
        return <ErrorState message={getErrorMessage(error)} onRetry={() => void refetch()} />
    }

    return <InvitationCard token={token} invitation={data} />
}

function InvitationCard({ token, invitation }: { token: string; invitation: InvitationPreview }) {
    const { user, logout } = useAuth()
    const navigate = useNavigate()
    const { showToast } = useToast()
    const accept = useAcceptInvitation()
    const decline = useDeclineInvitation()
    const [actionError, setActionError] = useState<string | null>(null)
    const [loggingOut, setLoggingOut] = useState(false)

    const displayStatus = getDisplayStatus(invitation)
    const returnPath = `/invitations/${encodeURIComponent(token)}`
    // Backend hanya menerima accept/decline dari akun dengan email yang sama dengan email undangan.
    const emailMismatch = user !== null && user.email.toLowerCase() !== invitation.email.toLowerCase()

    async function respond(action: 'accept' | 'decline') {
        setActionError(null)
        try {
            if (action === 'accept') {
                await accept.mutateAsync(token)
                showToast(`You joined ${invitation.project_name}.`)
            } else {
                await decline.mutateAsync(token)
                showToast('Invitation declined.')
            }
            navigate('/projects', { replace: true })
        } catch (error) {
            setActionError(describeActionError(error))
        }
    }

    async function handleLogout() {
        setActionError(null)
        setLoggingOut(true)
        try {
            await logout()
        } catch (error) {
            setActionError(getErrorMessage(error))
        } finally {
            setLoggingOut(false)
        }
    }

    function renderActions() {
        if (displayStatus !== 'pending') {
            return (
                <>
                    <p role="status" className="rounded-2xl bg-amber-50 px-3 py-2 text-sm text-amber-700">
                        {statusMessages[displayStatus]}
                    </p>
                    <Link to="/projects" className={secondaryLinkClass}>
                        Go to projects
                    </Link>
                </>
            )
        }

        if (!user) {
            return (
                <>
                    <p className="text-sm text-gray-600">
                        Log in or create an account with <span className="font-semibold">{invitation.email}</span> to
                        respond to this invitation.
                    </p>
                    <Link to={withRedirect('/login', returnPath)} className={primaryLinkClass}>
                        Login
                    </Link>
                    <Link to={withRedirect('/register', returnPath)} className={secondaryLinkClass}>
                        Create account
                    </Link>
                </>
            )
        }

        if (emailMismatch) {
            return (
                <>
                    <p role="status" className="rounded-2xl bg-amber-50 px-3 py-2 text-sm text-amber-700">
                        You are signed in as <span className="font-semibold">{user.email}</span>, but this invitation was
                        sent to <span className="font-semibold">{invitation.email}</span>. Log out and sign in with the
                        invited address to respond.
                    </p>
                    <Button type="button" variant="secondary" loading={loggingOut} onClick={() => void handleLogout()}>
                        Log out
                    </Button>
                </>
            )
        }

        return (
            <>
                <Button
                    type="button"
                    loading={accept.isPending}
                    disabled={decline.isPending}
                    onClick={() => void respond('accept')}
                >
                    Accept invitation
                </Button>
                <Button
                    type="button"
                    variant="secondary"
                    loading={decline.isPending}
                    disabled={accept.isPending}
                    onClick={() => void respond('decline')}
                >
                    Decline
                </Button>
            </>
        )
    }

    return (
        <div className="space-y-4">
            <div>
                <h1 className="mb-1 text-2xl font-bold text-gray-800">You're invited</h1>
                <p className="text-sm text-gray-600">
                    Join <span className="font-semibold">{invitation.project_name}</span> on TaskFlow.
                </p>
            </div>

            <dl className="space-y-2 rounded-2xl bg-gray-50 p-4 text-sm">
                <div className="flex items-center justify-between gap-3">
                    <dt className="text-gray-500">Role</dt>
                    <dd>
                        <RoleBadge role={invitation.role} />
                    </dd>
                </div>
                <div className="flex items-start justify-between gap-3">
                    <dt className="text-gray-500">Sent to</dt>
                    <dd className="font-medium break-all text-gray-800">{invitation.email}</dd>
                </div>
                {(displayStatus === 'pending' || displayStatus === 'expired') && (
                    <div className="flex items-center justify-between gap-3">
                        <dt className="text-gray-500">{displayStatus === 'expired' ? 'Expired' : 'Expires'}</dt>
                        <dd className="font-medium text-gray-800">{formatDate(invitation.expires_at)}</dd>
                    </div>
                )}
            </dl>

            {actionError && (
                <p role="alert" className="rounded-2xl bg-red-50 px-3 py-2 text-sm text-red-600">
                    {actionError}
                </p>
            )}

            {renderActions()}
        </div>
    )
}