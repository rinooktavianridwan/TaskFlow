import { Link } from 'react-router-dom'
import { formatDate } from '@/lib/dates'
import type { Project } from '../types'
import { RoleBadge } from './RoleBadge'

export function ProjectCard({ project }: { project: Project }) {
    return (
        <Link
            to={`/projects/${project.id}`}
            className="flex flex-col rounded-2xl border border-gray-200 bg-white p-5 transition hover:border-blue-300 hover:shadow-sm"
        >
            <div className="flex items-start justify-between gap-3">
                <h2 className="line-clamp-1 font-semibold break-words text-gray-800">{project.name}</h2>
                <RoleBadge role={project.role} />
            </div>
            <p className="mt-2 line-clamp-2 min-h-10 text-sm break-words text-gray-600">
                {project.description ?? 'No description'}
            </p>
            <p className="mt-4 text-xs text-gray-400">Created {formatDate(project.created_at)}</p>
        </Link>
    )
}