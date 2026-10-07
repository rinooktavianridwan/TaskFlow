export type Profile = {
    id: number
    name: string
    email: string
    timezone: string
}

// Minimal salah satu field. Email tidak bisa diubah dari sini.
export type UpdateProfilePayload = {
    name?: string
    timezone?: string
}

export type UpdatePasswordPayload = {
    current_password: string
    password: string
    password_confirmation: string
}