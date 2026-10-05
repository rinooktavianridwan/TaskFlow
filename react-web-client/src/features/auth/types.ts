export type User = {
    id: number
    name: string
    email: string
    email_verified_at: string | null
    created_at: string
    updated_at: string
}

export type LoginPayload = {
    email: string
    password: string
}

export type RegisterPayload = {
    name: string
    email: string
    password: string
    password_confirmation: string
}

export type RegisterResponse = {
    message: string
    email: string
}

export type VerifyRegistrationPayload = {
    email: string
    otp_code: string
}

export type ForgotPasswordPayload = {
    email: string
}

export type ResetPasswordPayload = {
    token: string
    email: string
    password: string
    password_confirmation: string
}