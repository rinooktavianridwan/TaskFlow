import { useId, type ComponentProps } from 'react'

type TextAreaFieldProps = ComponentProps<'textarea'> & {
    label: string
    error?: string
}

export function TextAreaField({ label, error, className = '', ...props }: TextAreaFieldProps) {
    const id = useId()
    const errorId = `${id}-error`

    return (
        <div>
            <label htmlFor={id} className="sr-only">
                {label}
            </label>
            <div
                className={`rounded-2xl border-2 px-3 py-2 focus-within:border-blue-500 ${error ? 'border-red-400' : 'border-gray-200'
                    }`}
            >
                <textarea
                    id={id}
                    rows={4}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={error ? errorId : undefined}
                    className={`w-full resize-none border-none outline-none ${className}`}
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