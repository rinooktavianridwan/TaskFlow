import axios, { isAxiosError, type InternalAxiosRequestConfig } from 'axios'

const apiUrl = import.meta.env.VITE_API_URL

if (!apiUrl) {
    throw new Error('VITE_API_URL belum dikonfigurasi.')
}

export const http = axios.create({
    baseURL: apiUrl.replace(/\/$/, ''),
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
})

export async function initializeCsrfCookie(): Promise<void> {
    await http.get('/sanctum/csrf-cookie')
}

type RetryableConfig = InternalAxiosRequestConfig & { _csrfRetried?: boolean }

// 419 = token CSRF kedaluwarsa/berganti. Ambil cookie baru, ulangi request satu kali.
http.interceptors.response.use(
    (response) => response,
    async (error: unknown) => {
        if (isAxiosError(error) && error.response?.status === 419 && error.config) {
            const config = error.config as RetryableConfig
            if (!config._csrfRetried) {
                config._csrfRetried = true
                await initializeCsrfCookie()
                return http(config)
            }
        }
        return Promise.reject(error)
    },
)