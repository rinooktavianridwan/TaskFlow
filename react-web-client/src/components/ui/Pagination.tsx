import type { PaginationMeta } from '@/types/api'
import { Button } from './Button'

type PaginationProps = {
    meta: PaginationMeta
    onPageChange: (page: number) => void
}

export function Pagination({ meta, onPageChange }: PaginationProps) {
    const totalPages = Math.max(1, Math.ceil(meta.total / meta.per_page))
    const page = meta.current_page

    if (totalPages <= 1) return null

    return (
        <nav aria-label="Pagination" className="mt-6 flex items-center justify-between text-sm text-gray-600">
            <span>
                Page {page} of {totalPages}
            </span>
            <div className="flex gap-2">
                <Button type="button" variant="secondary" fullWidth={false} disabled={page <= 1} onClick={() => onPageChange(page - 1)}>
                    Previous
                </Button>
                <Button type="button" variant="secondary" fullWidth={false} disabled={page >= totalPages} onClick={() => onPageChange(page + 1)}>
                    Next
                </Button>
            </div>
        </nav>
    )
}