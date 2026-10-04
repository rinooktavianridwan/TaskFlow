import { Button } from './Button'
import { Modal } from './Modal'

type ConfirmDialogProps = {
    open: boolean
    title: string
    message: string
    confirmLabel?: string
    loading?: boolean
    error?: string | null
    onConfirm: () => void
    onCancel: () => void
}

export function ConfirmDialog({
    open,
    title,
    message,
    confirmLabel = 'Confirm',
    loading = false,
    error,
    onConfirm,
    onCancel,
}: ConfirmDialogProps) {
    return (
        <Modal open={open} onClose={onCancel} title={title}>
            <p className="text-sm text-gray-600">{message}</p>

            {error && (
                <p role="alert" className="mt-4 rounded-2xl bg-red-50 px-3 py-2 text-sm text-red-600">
                    {error}
                </p>
            )}

            <div className="mt-6 flex justify-end gap-3">
                <Button type="button" variant="secondary" fullWidth={false} onClick={onCancel} disabled={loading}>
                    Cancel
                </Button>
                <Button type="button" variant="danger" fullWidth={false} onClick={onConfirm} loading={loading}>
                    {confirmLabel}
                </Button>
            </div>
        </Modal>
    )
}