type ProgressBarProps = {
    value: number
    label: string
    className?: string
}

export function ProgressBar({ value, label, className = '' }: ProgressBarProps) {
    const percent = Math.min(100, Math.max(0, Math.round(value)))

    return (
        <div
            role="progressbar"
            aria-label={label}
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={percent}
            className={`h-2 overflow-hidden rounded-full bg-gray-100 ${className}`}
        >
            <div
                className={`h-full rounded-full transition-[width] ${percent === 100 ? 'bg-green-500' : 'bg-blue-600'}`}
                style={{ width: `${percent}%` }}
            />
        </div>
    )
}