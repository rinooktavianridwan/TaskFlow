import type { ReactNode } from 'react'
import { Button } from '@/components/ui/Button'

export function LoadingState({ label = 'Loading…' }: { label?: string }) {
    return (
        <div role="status" className="py-16 text-center text-sm text-gray-500">
            {label}
        </div>
    )
}

type ErrorStateProps = {
    title?: string
    message?: string
    onRetry?: () => void
}

export function ErrorState({ title = 'Something went wrong', message, onRetry }: ErrorStateProps) {
    return (
        <div role="alert" className="mx-auto max-w-md rounded-3xl bg-white p-8 text-center ring-1 ring-gray-200">
            <h2 className="text-lg font-bold text-gray-800">{title}</h2>
            {message && <p className="mt-2 text-sm text-gray-600">{message}</p>}
            {onRetry && (
                <Button type="button" variant="secondary" fullWidth={false} className="mt-4" onClick={onRetry}>
                    Try again
                </Button>
            )}
        </div>
    )
}

type EmptyStateProps = {
    title: string
    description?: string
    action?: ReactNode
}

export function EmptyState({ title, description, action }: EmptyStateProps) {
    return (
        <div className="rounded-3xl border-2 border-dashed border-gray-200 px-6 py-14 text-center">
            <h2 className="text-lg font-bold text-gray-800">{title}</h2>
            {description && <p className="mt-2 text-sm text-gray-600">{description}</p>}
            {action && <div className="mt-4">{action}</div>}
        </div>
    )
}