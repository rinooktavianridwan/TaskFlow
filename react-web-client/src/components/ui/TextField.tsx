import { useId, type ComponentProps, type ReactNode } from 'react'

type TextFieldProps = ComponentProps<'input'> & {
    label: string
    showLabel?: boolean
    icon?: ReactNode
    error?: string
}

export function TextField({ label, showLabel = false, icon, error, className = '', ...props }: TextFieldProps) {
    const id = useId()
    const errorId = `${id}-error`

    return (
        <div>
            <label
                htmlFor={id}
                className={showLabel ? 'mb-1 ml-2 block text-sm font-medium text-gray-700' : 'sr-only'}
            >
                {label}
            </label>
            <div
                className={`flex items-center rounded-2xl border-2 px-3 py-2 focus-within:border-blue-500 ${error ? 'border-red-400' : 'border-gray-200'
                    }`}
            >
                {icon}
                <input
                    id={id}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={error ? errorId : undefined}
                    className={`w-full border-none pl-2 outline-none ${className}`}
                    {...props}
                />
            </div>
            {error && (
                <p id={errorId} className="mt-1 ml-2 text-sm text-red-500">
                    {error}
                </p>
            )}
        </div>
    )
}
