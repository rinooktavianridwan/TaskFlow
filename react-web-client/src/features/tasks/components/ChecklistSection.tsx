import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Button } from '@/components/ui/Button'
import { ProgressBar } from '@/components/ui/ProgressBar'
import { TextField } from '@/components/ui/TextField'
import { applyFieldErrors, getErrorMessage } from '@/lib/form-errors'
import { useAddChecklistItem, useDeleteChecklistItem, useUpdateChecklistItem } from '../queries'
import { checklistItemSchema, type ChecklistItemFormValues } from '../schemas'
import type { ChecklistItem, Task } from '../types'

const MAX_CHECKLIST_ITEMS = 50

type ChecklistSectionProps = {
    task: Task
    // Tambah, ganti judul, hapus: owner dan editor.
    canEdit: boolean
    // Mencentang: owner, editor, atau viewer yang ditugaskan.
    canToggle: boolean
}

export function ChecklistSection({ task, canEdit, canToggle }: ChecklistSectionProps) {
    const items = task.checklist ?? []
    const addItem = useAddChecklistItem(task.id)

    // Checklist bersifat opsional: tanpa item dan tanpa hak menambah, tidak ada yang perlu ditampilkan.
    if (items.length === 0 && !canEdit) return null

    return (
        <section className="mt-6 rounded-2xl border border-gray-200 bg-white p-6">
            <div className="flex items-center justify-between gap-3">
                <h2 className="text-lg font-bold text-gray-800">Checklist</h2>
                {task.checklist_total > 0 && (
                    <span className="text-sm text-gray-500 tabular-nums">
                        {task.checklist_done}/{task.checklist_total} done · {task.progress}%
                    </span>
                )}
            </div>

            {task.checklist_total > 0 && (
                <>
                    <ProgressBar value={task.progress} label="Task progress" className="mt-3" />
                    {/* Status done yang diatur manual membuat progress 100 walau item belum tercentang semua. */}
                    {task.status === 'done' && task.checklist_done < task.checklist_total && (
                        <p className="mt-1 text-xs text-gray-400">
                            Marked as done manually, so progress shows 100%.
                        </p>
                    )}
                </>
            )}

            {items.length > 0 && (
                <ul className="mt-4 space-y-2">
                    {items.map((item) => (
                        <ChecklistItemRow
                            key={item.id}
                            task={task}
                            item={item}
                            canEdit={canEdit}
                            canToggle={canToggle}
                        />
                    ))}
                </ul>
            )}

            {canEdit && (
                <div className="mt-4">
                    {items.length < MAX_CHECKLIST_ITEMS ? (
                        <ChecklistTitleForm
                            label="New checklist item"
                            placeholder="Add an item"
                            submitLabel="Add"
                            onSubmit={(title) => addItem.mutateAsync({ title })}
                        />
                    ) : (
                        <p className="text-sm text-gray-500">
                            A task can have at most {MAX_CHECKLIST_ITEMS} checklist items.
                        </p>
                    )}
                </div>
            )}
        </section>
    )
}

type ChecklistItemRowProps = {
    task: Task
    item: ChecklistItem
    canEdit: boolean
    canToggle: boolean
}

function ChecklistItemRow({ task, item, canEdit, canToggle }: ChecklistItemRowProps) {
    const updateItem = useUpdateChecklistItem()
    const deleteItem = useDeleteChecklistItem(task)
    const [editing, setEditing] = useState(false)
    const [error, setError] = useState<string | null>(null)
    const busy = updateItem.isPending || deleteItem.isPending

    async function handleToggle(isDone: boolean) {
        setError(null)
        try {
            // Hanya `is_done`: viewer yang ditugaskan tidak boleh mengirim field lain.
            await updateItem.mutateAsync({ itemId: item.id, payload: { is_done: isDone } })
        } catch (error) {
            setError(getErrorMessage(error))
        }
    }

    async function handleRename(title: string) {
        if (title !== item.title) {
            await updateItem.mutateAsync({ itemId: item.id, payload: { title } })
        }
        setEditing(false)
    }

    async function handleDelete() {
        setError(null)
        try {
            await deleteItem.mutateAsync(item.id)
        } catch (error) {
            setError(getErrorMessage(error))
        }
    }

    return (
        <li className="rounded-xl border border-gray-100 px-3 py-2">
            {editing ? (
                <ChecklistTitleForm
                    label="Item title"
                    placeholder="Item title"
                    submitLabel="Save"
                    initialTitle={item.title}
                    onSubmit={handleRename}
                    onCancel={() => setEditing(false)}
                />
            ) : (
                <div className="flex items-center gap-3">
                    {/* Nilai berasal dari cache, jadi jika request gagal centang otomatis kembali. */}
                    <input
                        type="checkbox"
                        checked={item.is_done}
                        disabled={!canToggle || busy}
                        onChange={(event) => void handleToggle(event.target.checked)}
                        aria-label={`Mark "${item.title}" as done`}
                        className="h-4 w-4 shrink-0 accent-blue-600"
                    />
                    <span
                        className={`min-w-0 flex-1 text-sm break-words ${item.is_done ? 'text-gray-400 line-through' : 'text-gray-800'}`}
                    >
                        {item.title}
                    </span>
                    {canEdit && (
                        <div className="flex shrink-0 gap-3 text-xs">
                            <button
                                type="button"
                                disabled={busy}
                                onClick={() => setEditing(true)}
                                className="font-medium text-gray-500 hover:text-blue-600 disabled:opacity-60"
                            >
                                Edit
                            </button>
                            <button
                                type="button"
                                disabled={busy}
                                onClick={() => void handleDelete()}
                                className="font-medium text-gray-500 hover:text-red-600 disabled:opacity-60"
                            >
                                Delete
                            </button>
                        </div>
                    )}
                </div>
            )}
            {error && (
                <p role="alert" className="mt-1 text-xs text-red-500">
                    {error}
                </p>
            )}
        </li>
    )
}

type ChecklistTitleFormProps = {
    label: string
    placeholder: string
    submitLabel: string
    initialTitle?: string
    onSubmit: (title: string) => Promise<unknown>
    onCancel?: () => void
}

// Dipakai untuk tambah item dan ganti judul: satu field judul.
function ChecklistTitleForm({
    label,
    placeholder,
    submitLabel,
    initialTitle = '',
    onSubmit,
    onCancel,
}: ChecklistTitleFormProps) {
    const [formError, setFormError] = useState<string | null>(null)
    const {
        register,
        handleSubmit,
        setError,
        reset,
        formState: { errors, isSubmitting },
    } = useForm<ChecklistItemFormValues>({
        resolver: zodResolver(checklistItemSchema),
        defaultValues: { title: initialTitle },
    })

    const submit = handleSubmit(async (values) => {
        setFormError(null)
        try {
            await onSubmit(values.title)
            reset({ title: initialTitle })
        } catch (error) {
            // 422 key `title`: judul tidak valid atau sudah 50 item.
            if (!applyFieldErrors(error, setError)) setFormError(getErrorMessage(error))
        }
    })

    return (
        <form onSubmit={submit} noValidate>
            <div className="flex items-start gap-2">
                <div className="min-w-0 flex-1">
                    <TextField
                        label={label}
                        type="text"
                        placeholder={placeholder}
                        maxLength={255}
                        error={errors.title?.message}
                        {...register('title')}
                    />
                </div>
                <Button type="submit" fullWidth={false} loading={isSubmitting}>
                    {submitLabel}
                </Button>
                {onCancel && (
                    <Button type="button" variant="secondary" fullWidth={false} disabled={isSubmitting} onClick={onCancel}>
                        Cancel
                    </Button>
                )}
            </div>
            {formError && (
                <p role="alert" className="mt-1 ml-2 text-sm text-red-500">
                    {formError}
                </p>
            )}
        </form>
    )
}