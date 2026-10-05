const ALLOWED_PREFIX = '/'

// Terima hanya path internal aplikasi. Return null bila kosong atau tidak aman.
// Dicek dengan parser URL (bukan sekadar startsWith) karena browser menormalkan
// input seperti "/\evil.com" atau "/<tab>/evil.com" menjadi "//evil.com" (situs lain).
export function getSafeRedirect(raw: string | null): string | null {
    if (!raw || !raw.startsWith(ALLOWED_PREFIX)) return null

    try {
        const url = new URL(raw, window.location.origin)
        if (url.origin !== window.location.origin) return null
        return `${url.pathname}${url.search}${url.hash}`
    } catch {
        return null
    }
}

// Bangun URL halaman auth yang membawa ?redirect= (bila ada) plus parameter lain.
export function withRedirect(
    path: string,
    redirect: string | null,
    params: Record<string, string> = {},
): string {
    const search = new URLSearchParams(params)
    if (redirect) search.set('redirect', redirect)

    const query = search.toString()
    return query ? `${path}?${query}` : path
}