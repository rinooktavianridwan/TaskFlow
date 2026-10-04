import { useEffect, useId, useRef, type ReactNode } from 'react'

type ModalProps = {
    open: boolean
    onClose: () => void
    title: string
    children: ReactNode
}

export function Modal({ open, onClose, title, children }: ModalProps) {
    const ref = useRef<HTMLDialogElement>(null)
    const titleId = useId()

    useEffect(() => {
        const dialog = ref.current
        if (!dialog) return
        if (open && !dialog.open) dialog.showModal()
        if (!open && dialog.open) dialog.close()
    }, [open])

    return (
        <dialog
            ref={ref}
            aria-labelledby={titleId}
            onClose={onClose}
            // Klik pada latar (::backdrop) dilaporkan sebagai klik pada <dialog> itu sendiri.
            onClick={(event) => {
                if (event.target === event.currentTarget) onClose()
            }}
            className="m-auto w-full max-w-md rounded-2xl p-0 shadow-xl backdrop:bg-black/40"
        >
            {/* Isi hanya dirender saat terbuka, sehingga form selalu mulai dari keadaan segar. */}
            {open && (
                <div className="p-6">
                    <h2 id={titleId} className="mb-4 text-lg font-bold text-gray-800">
                        {title}
                    </h2>
                    {children}
                </div>
            )}
        </dialog>
    )
}