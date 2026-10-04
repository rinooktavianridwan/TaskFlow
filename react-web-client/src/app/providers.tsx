import { MutationCache, QueryCache, QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ReactQueryDevtools } from '@tanstack/react-query-devtools'
import { isAxiosError } from 'axios'
import type { ReactNode } from 'react'
import { AuthProvider } from '@/features/auth/AuthProvider'
import { authKeys } from '@/features/auth/queries'

// Satu tempat untuk sesi habis: error 401 dari request apa pun -> anggap logout.
// Guard akan otomatis mengarahkan ke /login.
function handleError(error: unknown) {
    if (isAxiosError(error) && error.response?.status === 401) {
        queryClient.setQueryData(authKeys.user, null)
    }
}

const queryClient = new QueryClient({
    queryCache: new QueryCache({ onError: handleError }),
    mutationCache: new MutationCache({ onError: handleError }),
    defaultOptions: {
        queries: {
            retry: (failureCount, error) => {
                const status = isAxiosError(error) ? error.response?.status : undefined
                if (status !== undefined && status < 500) return false
                return failureCount < 1
            },
        },
    },
})

export function Providers({ children }: { children: ReactNode }) {
    return (
        <QueryClientProvider client={queryClient}>
            <AuthProvider>{children}</AuthProvider>
            <ReactQueryDevtools initialIsOpen={false} />
        </QueryClientProvider>
    )
}