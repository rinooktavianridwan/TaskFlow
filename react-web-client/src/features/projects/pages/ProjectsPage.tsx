import { useState } from 'react'
import { EmptyState, ErrorState, LoadingState } from '@/components/feedback/states'
import { Button } from '@/components/ui/Button'
import { Pagination } from '@/components/ui/Pagination'
import { SearchField } from '@/components/ui/SearchField'
import { getErrorMessage } from '@/lib/form-errors'
import { parsePage, toPageParam, useUrlParams } from '@/lib/use-url-params'
import { ProjectCard } from '../components/ProjectCard'
import { ProjectFormModal } from '../components/ProjectFormModal'
import { useProjects } from '../queries'

export function ProjectsPage() {
    const [params, updateParams] = useUrlParams()
    const [creating, setCreating] = useState(false)
    // Pencarian dan halaman disimpan di URL: tahan refresh dan bisa dibagikan.
    const search = (params.get('q') ?? '').slice(0, 100)
    const page = parsePage(params.get('page'))

    const { data, isPending, isError, error, refetch, isPlaceholderData } = useProjects({
        page,
        name: search || undefined,
    })

    function renderContent() {
        if (isPending) return <LoadingState />
        if (isError) return <ErrorState message={getErrorMessage(error)} onRetry={() => void refetch()} />

        if (data.items.length === 0) {
            return search ? (
                <EmptyState title="No projects found" description={`Nothing matches "${search}".`} />
            ) : (
                <EmptyState
                    title="No projects yet"
                    description="Create your first project to start organizing tasks with your team."
                    action={
                        <Button type="button" fullWidth={false} onClick={() => setCreating(true)}>
                            New project
                        </Button>
                    }
                />
            )
        }

        return (
            <div className={isPlaceholderData ? 'opacity-60 transition-opacity' : 'transition-opacity'}>
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {data.items.map((project) => (
                        <ProjectCard key={project.id} project={project} />
                    ))}
                </div>
                <Pagination meta={data.meta} onPageChange={(next) => updateParams({ page: toPageParam(next) })} />
            </div>
        )
    }

    return (
        <div>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-bold text-gray-800">Projects</h1>
                <Button type="button" fullWidth={false} onClick={() => setCreating(true)}>
                    New project
                </Button>
            </div>

            <div className="mt-4 max-w-sm">
                <SearchField
                    label="Search projects"
                    placeholder="Search projects"
                    maxLength={100}
                    initialValue={search}
                    onSearch={(value) => updateParams({ q: value, page: null })}
                />
            </div>

            <div className="mt-6">{renderContent()}</div>

            <ProjectFormModal open={creating} onClose={() => setCreating(false)} />
        </div>
    )
}