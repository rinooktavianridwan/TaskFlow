import { useId, useState, type ChangeEvent } from 'react'
import { getErrorMessage } from '@/lib/form-errors'
import { useUpdateTask } from '../queries'
import { TASK_STATUS_LABELS, TASK_STATUSES } from '../status'
import type { Task, TaskStatus } from '../types'
import { StatusBadge } from './StatusBadge'

type StatusSelectProps = {
    task: Task
    canChange: boolean
}

export function StatusSelect({ task, canChange }: StatusSelectProps) {
    const id = useId()
    const updateTask = useUpdateTask()
    const [error, setError] = useState<string | null>(null)

    if (!canChange) return <StatusBadge status={task.status} />

    async function handleChange(event: ChangeEvent<HTMLSelectElement>) {
        setError(null)
        try {
            // Hanya kirim `status`: viewer hanya boleh mengirim field ini (backend menolak selainnya).
            await updateTask.mutateAsync({ id: task.id, payload: { status: event.target.value as TaskStatus } })
        } catch (error) {
            setError(getErrorMessage(error))
        }
    }

    return (
        <div>
            <label htmlFor={id} className="sr-only">
                Status
            </label>
            {/* Nilai berasal dari cache, jadi jika request gagal pilihan otomatis kembali ke nilai semula. */}
            <select
                id={id}
                value={task.status}
                disabled={updateTask.isPending}
                onChange={(event) => void handleChange(event)}
                className="rounded-full border border-gray-200 bg-white px-3 py-1 text-sm font-medium text-gray-700 outline-none focus:border-blue-500 disabled:opacity-60"
            >
                {TASK_STATUSES.map((status) => (
                    <option key={status} value={status}>
                        {TASK_STATUS_LABELS[status]}
                    </option>
                ))}
            </select>
            {error && (
                <p role="alert" className="mt-1 text-xs text-red-500">
                    {error}
                </p>
            )}
        </div>
    )
}