export type ApiResponse<T> = {
    success: true
    message: string
    data: T
}

export type PaginationMeta = {
    current_page: number
    per_page: number
    total: number
}

export type Paginated<T> = {
    items: T[]
    meta: PaginationMeta
}

// 422 menyertakan `errors`; 419 dan 429 hanya `message`.
export type ApiErrorBody = {
    success?: false
    message: string
    errors?: Record<string, string[]>
}