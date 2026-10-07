// Inisial untuk avatar: huruf pertama kata pertama + huruf pertama kata terakhir.
export function getInitials(name: string): string {
    const words = name.trim().split(/\s+/).filter(Boolean)
    if (words.length === 0) return '?'

    // Array.from agar karakter di luar BMP (mis. emoji) tidak terpotong.
    const first = Array.from(words[0])[0]
    const last = words.length > 1 ? Array.from(words[words.length - 1])[0] : ''
    return `${first}${last}`.toUpperCase()
}