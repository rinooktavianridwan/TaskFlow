import { Link } from 'react-router-dom'
import { describeCompletedItems, formatSummaryDate, formatSummaryTime } from '../format'
import type { SummaryDay, SummaryEvent, SummaryProject, SummaryTask } from '../types'

type TimezoneProps = { timezone: string }

export function DaySummaryCard({ day, timezone }: { day: SummaryDay } & TimezoneProps) {
    return (
        <section className="rounded-2xl border border-gray-200 bg-white p-6">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h2 className="text-lg font-bold text-gray-800">{formatSummaryDate(day.date)}</h2>
                <span className="text-sm text-gray-500">
                    {day.total_events} {day.total_events === 1 ? 'event' : 'events'}
                </span>
            </div>
            <div className="mt-4 space-y-6">
                {day.projects.map((project) => (
                    <ProjectBlock key={project.project_id} project={project} timezone={timezone} />
                ))}
            </div>
        </section>
    )
}

function ProjectBlock({ project, timezone }: { project: SummaryProject } & TimezoneProps) {
    return (
        <div>
            <h3 className="font-semibold break-words text-blue-700">
                <Link to={`/projects/${project.project_id}`} className="hover:underline">
                    {project.project_name}
                </Link>
            </h3>
            {project.events.length > 0 && (
                <div className="mt-2">
                    <EventList events={project.events} timezone={timezone} />
                </div>
            )}
            {project.tasks.length > 0 && (
                <div className="mt-3 space-y-4 border-l-2 border-gray-100 pl-4">
                    {project.tasks.map((task) => (
                        <TaskBlock key={task.task_id} task={task} timezone={timezone} />
                    ))}
                </div>
            )}
        </div>
    )
}

function TaskBlock({ task, timezone }: { task: SummaryTask } & TimezoneProps) {
    const completed = describeCompletedItems(task)

    return (
        <div>
            <h4 className="font-medium break-words text-gray-800">
                {task.task_exists ? (
                    <Link to={`/tasks/${task.task_id}`} className="hover:text-blue-600">
                        {task.task_title}
                    </Link>
                ) : (
                    <>
                        {task.task_title} <span className="text-xs font-normal text-gray-400">(deleted)</span>
                    </>
                )}
            </h4>
            {completed && <p className="mt-1 text-sm font-medium text-green-700">{completed}</p>}
            <div className="mt-2">
                <EventList events={task.events} timezone={timezone} />
            </div>
        </div>
    )
}

function EventList({ events, timezone }: { events: SummaryEvent[] } & TimezoneProps) {
    return (
        <ul className="space-y-1">
            {events.map((event) => (
                <li key={event.id} className="flex gap-3 text-sm text-gray-600">
                    <time dateTime={event.created_at} className="w-12 shrink-0 text-xs text-gray-400 tabular-nums">
                        {formatSummaryTime(event.created_at, timezone)}
                    </time>
                    <span className="min-w-0 break-words">{event.description}</span>
                </li>
            ))}
        </ul>
    )
}