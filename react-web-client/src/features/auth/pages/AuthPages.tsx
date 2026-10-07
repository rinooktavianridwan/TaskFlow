import { PageFrame } from '../../../components/ui/PageFrame'

export function LoginPage() {
    return <PageFrame title="Login" description="Masuk ke akun TaskFlow." />
}

export function RegisterPage() {
    return <PageFrame title="Buat akun" description="Daftar untuk menggunakan TaskFlow." />
}

export function VerifyRegistrationPage() {
    return <PageFrame title="Verifikasi email" description="Masukkan OTP dari email Anda." />
}

export function ForgotPasswordPage() {
    return <PageFrame title="Lupa password" description="Minta tautan untuk mengatur ulang password." />
}

export function ResetPasswordPage() {
    return <PageFrame title="Atur ulang password" description="Buat password baru." />
}