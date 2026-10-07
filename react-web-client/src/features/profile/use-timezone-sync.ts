import { useEffect } from 'react'
import { useToast } from '@/components/feedback/toast-context'
import { useAuth } from '@/features/auth/auth-context'
import { useUpdateProfile } from './queries'
import { detectBrowserTimezone } from './timezones'

const FLAG_PREFIX = 'taskflow:timezone-applied:'
const UTC_ALIASES = ['UTC', 'Etc/UTC']

function hasFlag(key: string): boolean {
    try {
        return localStorage.getItem(key) !== null
    } catch {
        // Tanpa penyimpanan penanda tidak bisa dibuat: lebih aman tidak menyinkronkan sama sekali daripada menimpa pilihan manual user di setiap muat halaman.
        return true
    }
}

function setFlag(key: string): void {
    try {
        localStorage.setItem(key, '1')
    } catch {
        // Diabaikan: hasFlag() sudah melewati sinkronisasi saat penyimpanan tidak tersedia.
    }
}

// Akun baru berzona UTC (default). Sekali saja, terapkan zona browser bila berbeda.
// Penanda per user di localStorage mencegah penimpaan pilihan manual di kemudian hari.
export function useTimezoneSync(): void {
    const { user } = useAuth()
    const { showToast } = useToast()
    const { mutateAsync } = useUpdateProfile()
    const userId = user?.id
    const timezone = user?.timezone

    useEffect(() => {
        if (userId === undefined || timezone !== 'UTC') return

        const browserTimezone = detectBrowserTimezone()
        if (browserTimezone === null || UTC_ALIASES.includes(browserTimezone)) return

        const key = `${FLAG_PREFIX}${userId}`
        if (hasFlag(key)) return
        // Tandai lebih dulu: gagal tidak diulang tiap muat halaman (dan StrictMode tidak menembak dua kali).
        setFlag(key)

        mutateAsync({ timezone: browserTimezone })
            .then(() => showToast(`Timezone set to ${browserTimezone}. You can change it in your profile.`))
            .catch(() => undefined)
    }, [userId, timezone, mutateAsync, showToast])
}