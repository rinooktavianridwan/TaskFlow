import { useState } from 'react'
import { EmptyState, ErrorState, LoadingState } from '@/components/feedback/states'
import { Button } from '@/components/ui/Button'
import { SearchIcon } from '@/components/ui/icons'
import { Pagination } from '@/components/ui/Pagination'
import { TextField } from '@/components/ui/TextField'
import { getErrorMessage } from '@/lib/form-errors'
import { useDebouncedValue } from '@/lib/use-debounced-value'
import { ProjectCard } from '../components/ProjectCard'
import { ProjectFormModal } from '../components/ProjectFormModal'
import { useProjects } from '../queries'

export function ProjectsPage() {
    const [search, setSearch] = useState('')
    const [page, setPage] = useState(1)
    const [creating, setCreating] = useState(false)
    const debouncedSearch = useDebouncedValue(search.trim(), 300)

    const { data, isPending, isError, error, refetch, isPlaceholderData } = useProjects({
        page,
        name: debouncedSearch || undefined,
    })

    function renderContent() {
        if (isPending) return <LoadingState />
        if (isError) return <ErrorState message={getErrorMessage(error)} onRetry={() => void refetch()} />

        if (data.items.length === 0) {
            return debouncedSearch ? (
                <EmptyState title="No projects found" description={`Nothing matches "${debouncedSearch}".`} />
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
                <Pagination meta={data.meta} onPageChange={setPage} />
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
                <TextField
                    label="Search projects"
                    type="search"
                    placeholder="Search projects"
                    icon={<SearchIcon />}
                    maxLength={100}
                    value={search}
                    onChange={(event) => {
                        setSearch(event.target.value)
                        setPage(1)
                    }}
                />
            </div>

            <div className="mt-6">{renderContent()}</div>

            <ProjectFormModal open={creating} onClose={() => setCreating(false)} />
        </div>
    )
}