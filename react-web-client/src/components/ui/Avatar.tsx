import { getInitials } from '@/lib/initials'

type AvatarProps = {
    name: string
    size?: 'sm' | 'lg'
}

const sizeClasses = {
    sm: 'h-8 w-8 text-xs',
    lg: 'h-16 w-16 text-xl',
}

// Foto profil belum ada di backend: avatar placeholder berinisial.
export function Avatar({ name, size = 'sm' }: AvatarProps) {
    return (
        <span
            aria-hidden="true"
            className={`grid shrink-0 place-items-center rounded-full bg-blue-100 font-semibold text-blue-700 ${sizeClasses[size]}`}
        >
            {getInitials(name)}
        </span>
    )
}