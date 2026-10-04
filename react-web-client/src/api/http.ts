import axios from 'axios'

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