import { useId, type ComponentProps } from 'react'

type SelectFieldProps = ComponentProps<'select'> & {
    label: string
    error?: string
}

export function SelectField({ label, error, className = '', children, ...props }: SelectFieldProps) {
    const id = useId()
    const errorId = `${id}-error`

    return (
        <div>
            <label htmlFor={id} className="mb-1 ml-2 block text-sm font-medium text-gray-700">
                {label}
            </label>
            <div
                className={`rounded-2xl border-2 px-3 py-2 focus-within:border-blue-500 ${error ? 'border-red-400' : 'border-gray-200'
                    }`}
            >
                <select
                    id={id}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={error ? errorId : undefined}
                    className={`w-full bg-transparent outline-none ${className}`}
                    {...props}
                >
                    {children}
                </select>
            </div>
            {error && (
                <p id={errorId} className="mt-1 ml-2 text-sm text-red-500">
                    {error}
                </p>
            )}
        </div>
    )
}