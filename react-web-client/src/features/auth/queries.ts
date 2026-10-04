import { queryOptions } from '@tanstack/react-query'
import { fetchCurrentUser } from './api'

export const authKeys = {
    user: ['auth', 'user'] as const,
}

export const currentUserQueryOptions = queryOptions({
    queryKey: authKeys.user,
    queryFn: fetchCurrentUser,
    staleTime: 5 * 60_000,
    retry: false,
})