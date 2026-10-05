import { useCallback, useMemo, useRef, useState, type ReactNode } from 'react'
import { ToastContext, type ToastVariant } from './toast-context'

type Toast = { id: number; message: string; variant: ToastVariant }

const DISMISS_AFTER_MS = 4000

const variantStyles: Record<ToastVariant, string> = {
    success: 'bg-green-600 text-white',
    error: 'bg-red-600 text-white',
}

export function ToastProvider({ children }: { children: ReactNode }) {
    const [toasts, setToasts] = useState<Toast[]>([])
    const nextId = useRef(0)

    const dismiss = useCallback((id: number) => {
        setToasts((current) => current.filter((toast) => toast.id !== id))
    }, [])

    const showToast = useCallback(
        (message: string, variant: ToastVariant = 'success') => {
            const id = nextId.current++
            setToasts((current) => [...current, { id, message, variant }])
            setTimeout(() => dismiss(id), DISMISS_AFTER_MS)
        },
        [dismiss],
    )

    const value = useMemo(() => ({ showToast }), [showToast])

    return (
        <ToastContext value={value}>
            {children}
            <div
                aria-live="polite"
                className="pointer-events-none fixed inset-x-4 bottom-4 z-50 ml-auto flex max-w-sm flex-col gap-2"
            >
                {toasts.map((toast) => (
                    <div
                        key={toast.id}
                        role="status"
                        className={`pointer-events-auto flex items-start justify-between gap-3 rounded-2xl px-4 py-3 text-sm shadow-lg ${variantStyles[toast.variant]}`}
                    >
                        <span>{toast.message}</span>
                        <button
                            type="button"
                            onClick={() => dismiss(toast.id)}
                            aria-label="Dismiss notification"
                            className="font-semibold opacity-80 hover:opacity-100"
                        >
                            ×
                        </button>
                    </div>
                ))}
            </div>
        </ToastContext>
    )
}