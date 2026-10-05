import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { EmptyState, ErrorState, LoadingState } from '@/components/feedback/states'
import { useToast } from '@/components/feedback/toast-context'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { Pagination } from '@/components/ui/Pagination'
import { SearchField } from '@/components/ui/SearchField'
import { useAuth } from '@/features/auth/auth-context'
import { canManageProject } from '@/features/projects/permissions'
import { useProject } from '@/features/projects/queries'
import { PROJECT_ROLE_LABELS } from '@/features/projects/roles'
import type { ProjectRole } from '@/features/projects/types'
import { getErrorMessage } from '@/lib/form-errors'
import { parsePage, toPageParam, useUrlParams } from '@/lib/use-url-params'
import { MemberRow } from '../components/MemberRow'
import { useMembers, useRemoveMember, useUpdateMemberRole } from '../queries'
import type { Member } from '../types'

export function ProjectMembersPage() {
    const { projectId } = useParams()
    const id = Number(projectId)
    const navigate = useNavigate()
    const { user } = useAuth()
    const { showToast } = useToast()
    // Layout induk sudah memuat project, jadi ini langsung terisi dari cache.
    const { data: project } = useProject(id)
    const canManage = canManageProject(project?.role)

    const [params, updateParams] = useUrlParams()
    const [pendingRemoval, setPendingRemoval] = useState<Member | null>(null)
    const [removeError, setRemoveError] = useState<string | null>(null)
    const [actionError, setActionError] = useState<string | null>(null)
    // Pencarian dan halaman disimpan di URL: tahan refresh dan bisa dibagikan.
    const search = (params.get('q') ?? '').slice(0, 255)
    const page = parsePage(params.get('page'))

    const { data, isPending, isError, error, refetch, isPlaceholderData } = useMembers(
        id,
        { page, name: search || undefined },
        { keepPreviousData: true },
    )
    const updateRole = useUpdateMemberRole(id)
    const removeMember = useRemoveMember(id)

    const removingSelf = pendingRemoval !== null && pendingRemoval.user_id === user?.id

    async function handleRoleChange(member: Member, role: ProjectRole) {
        setActionError(null)
        try {
            await updateRole.mutateAsync({ userId: member.user_id, role })
            showToast(`${member.name} is now ${PROJECT_ROLE_LABELS[role].toLowerCase()}.`)
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
            if (member.user_id === user?.id) {
                showToast('You left the project.')
                navigate('/projects', { replace: true })
            } else {
                showToast(`${member.name} was removed from the project.`)
            }
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
            return <EmptyState title="No members found" description={`Nothing matches "${search}".`} />
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
                <Pagination meta={data.meta} onPageChange={(next) => updateParams({ page: toPageParam(next) })} />
            </div>
        )
    }

    return (
        <div>
            <div className="w-full sm:w-64">
                <SearchField
                    label="Search members"
                    placeholder="Search members"
                    maxLength={255}
                    initialValue={search}
                    onSearch={(value) => updateParams({ q: value, page: null })}
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