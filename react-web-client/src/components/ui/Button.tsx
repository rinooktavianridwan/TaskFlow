import type { ComponentProps } from 'react'

type Variant = 'primary' | 'secondary' | 'danger'

type ButtonProps = ComponentProps<'button'> & {
    variant?: Variant
    fullWidth?: boolean
    loading?: boolean
}

const variantClasses: Record<Variant, string> = {
    primary: 'bg-blue-600 text-white hover:bg-blue-700',
    secondary: 'border border-gray-200 bg-white text-gray-700 hover:bg-gray-50',
    danger: 'bg-red-600 text-white hover:bg-red-700',
}

export function Button({
    variant = 'primary',
    fullWidth = true,
    loading = false,
    disabled,
    className = '',
    children,
    ...props
}: ButtonProps) {
    return (
        <button
            disabled={disabled || loading}
            className={`rounded-2xl px-4 py-2 font-semibold transition disabled:cursor-not-allowed disabled:opacity-60 ${fullWidth ? 'block w-full' : 'inline-flex items-center justify-center'
                } ${variantClasses[variant]} ${className}`}
            {...props}
        >
            {loading ? 'Please wait…' : children}
        </button>
    )
}