import type { ComponentProps } from 'react'

type ButtonProps = ComponentProps<'button'> & { loading?: boolean }

export function Button({ loading = false, disabled, className = '', children, ...props }: ButtonProps) {
    return (
        <button
            disabled={disabled || loading}
            className={`block w-full rounded-2xl bg-blue-600 py-2 font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60 ${className}`}
            {...props}
        >
            {loading ? 'Please wait…' : children}
        </button>
    )
}