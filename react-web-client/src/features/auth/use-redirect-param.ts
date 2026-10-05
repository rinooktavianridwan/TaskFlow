import { useSearchParams } from 'react-router-dom'
import { getSafeRedirect } from '@/lib/redirect'

// Tujuan awal dari ?redirect=, sudah divalidasi. null = tidak ada atau tidak aman.
export function useRedirectParam(): string | null {
    const [searchParams] = useSearchParams()
    return getSafeRedirect(searchParams.get('redirect'))
}