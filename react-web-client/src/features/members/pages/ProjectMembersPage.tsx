import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { EmptyState, ErrorState, LoadingState } from '@/components/feedback/states'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { SearchIcon } from '@/components/ui/icons'
import { Pagination } from '@/components/ui/Pagination'
import { TextField } from '@/components/ui/TextField'
import { useAuth } from '@/features/auth/auth-context'
import { canManageProject } from '@/features/projects/permissions'
import { useProject } from '@/features/projects/queries'
import type { ProjectRole } from '@/features/projects/types'
import { getErrorMessage } from '@/lib/form-errors'
import { useDebouncedValue } from '@/lib/use-debounced-value'
import { MemberRow } from '../components/MemberRow'
import { useMembers, useRemoveMember, useUpdateMemberRole } from '../queries'
import type { Member } from '../types'

export function ProjectMembersPage() {
    const { projectId } = useParams()
    const id = Number(projectId)
    const navigate = useNavigate()
    const { user } = useAuth()
    // Layout induk sudah memuat project, jadi ini langsung terisi dari cache.
    const { data: project } = useProject(id)
    const canManage = canManageProject(project?.role)

    const [search, setSearch] = useState('')
    const [page, setPage] = useState(1)
    const [pendingRemoval, setPendingRemoval] = useState<Member | null>(null)
    const [removeError, setRemoveError] = useState<string | null>(null)
    const [actionError, setActionError] = useState<string | null>(null)
    const debouncedSearch = useDebouncedValue(search.trim(), 300)

    const { data, isPending, isError, error, refetch, isPlaceholderData } = useMembers(
        id,
        { page, name: debouncedSearch || undefined },
        { keepPreviousData: true },
    )
    const updateRole = useUpdateMemberRole(id)
    const removeMember = useRemoveMember(id)

    const removingSelf = pendingRemoval !== null && pendingRemoval.user_id === user?.id

    async function handleRoleChange(member: Member, role: ProjectRole) {
        setActionError(null)
        try {
            await updateRole.mutateAsync({ userId: member.user_id, role })
        } catch (error) {
            // Mis. "A project must always have at least one owner."
            setActionError(getErrorMessage(error))
        }
    }

    async function handleRemove(member: Member) {
        setRemoveError(null)
        try {
            await removeMember.mutateAsync(member.user_id)
            setPendingRemoval(null)
            if (member.user_id === user?.id) navigate('/projects', { replace: true })
        } catch (error) {
            setRemoveError(getErrorMessage(error))
        }
    }

    function openRemoval(member: Member) {
        setRemoveError(null)
        setPendingRemoval(member)
    }

    function closeRemoval() {
        setPendingRemoval(null)
        setRemoveError(null)
    }

    function renderContent() {
        if (isPending) return <LoadingState />
        if (isError) return <ErrorState message={getErrorMessage(error)} onRetry={() => void refetch()} />

        if (data.items.length === 0) {
            return <EmptyState title="No members found" description={`Nothing matches "${debouncedSearch}".`} />
        }

        return (
            <div className={isPlaceholderData ? 'opacity-60 transition-opacity' : 'transition-opacity'}>
                <ul className="space-y-3">
                    {data.items.map((member) => (
                        <MemberRow
                            key={member.user_id}
                            member={member}
                            isSelf={member.user_id === user?.id}
                            canManage={canManage}
                            busy={updateRole.isPending}
                            onChangeRole={(target, role) => void handleRoleChange(target, role)}
                            onRemove={openRemoval}
                        />
                    ))}
                </ul>
                <Pagination meta={data.meta} onPageChange={setPage} />
            </div>
        )
    }

    return (
        <div>
            <div className="w-full sm:w-64">
                <TextField
                    label="Search members"
                    type="search"
                    placeholder="Search members"
                    icon={<SearchIcon />}
                    maxLength={255}
                    value={search}
                    onChange={(event) => {
                        setSearch(event.target.value)
                        setPage(1)
                    }}
                />
            </div>

            {actionError && (
                <p role="alert" className="mt-4 rounded-2xl bg-red-50 px-3 py-2 text-sm text-red-600">
                    {actionError}
                </p>
            )}

            <div className="mt-6">{renderContent()}</div>

            <ConfirmDialog
                open={pendingRemoval !== null}
                title={removingSelf ? 'Leave project' : 'Remove member'}
                message={
                    removingSelf
                        ? `You will lose access to "${project?.name ?? 'this project'}". Tasks assigned to you will be unassigned.`
                        : `${pendingRemoval?.name ?? 'This member'} will lose access to this project. Their assigned tasks will be unassigned.`
                }
                confirmLabel={removingSelf ? 'Leave project' : 'Remove member'}
                loading={removeMember.isPending}
                error={removeError}
                onConfirm={() => {
                    if (pendingRemoval) void handleRemove(pendingRemoval)
                }}
                onCancel={closeRemoval}
            />
        </div>
    )
}