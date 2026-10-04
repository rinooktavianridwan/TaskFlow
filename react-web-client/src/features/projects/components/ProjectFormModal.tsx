import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useNavigate } from 'react-router-dom'
import { Button } from '@/components/ui/Button'
import { Modal } from '@/components/ui/Modal'
import { TextAreaField } from '@/components/ui/TextAreaField'
import { TextField } from '@/components/ui/TextField'
import { applyFieldErrors, getErrorMessage } from '@/lib/form-errors'
import { useCreateProject, useUpdateProject } from '../queries'
import { projectSchema, type ProjectFormValues } from '../schemas'
import type { Project } from '../types'

type ProjectFormModalProps = {
    open: boolean
    onClose: () => void
    project?: Project
}

export function ProjectFormModal({ open, onClose, project }: ProjectFormModalProps) {
    return (
        <Modal open={open} onClose={onClose} title={project ? 'Edit project' : 'New project'}>
            <ProjectForm project={project} onClose={onClose} />
        </Modal>
    )
}

function ProjectForm({ project, onClose }: { project?: Project; onClose: () => void }) {
    const navigate = useNavigate()
    const createProject = useCreateProject()
    const updateProject = useUpdateProject()
    const [formError, setFormError] = useState<string | null>(null)
    const {
        register,
        handleSubmit,
        setError,
        formState: { errors, isSubmitting },
    } = useForm<ProjectFormValues>({
        resolver: zodResolver(projectSchema),
        defaultValues: { name: project?.name ?? '', description: project?.description ?? '' },
    })

    const onSubmit = handleSubmit(async (values) => {
        setFormError(null)
        // Deskripsi kosong dikirim sebagai null supaya benar-benar mengosongkan kolom di database.
        const payload = { name: values.name, description: values.description || null }

        try {
            if (project) {
                await updateProject.mutateAsync({ id: project.id, payload })
                onClose()
            } else {
                const created = await createProject.mutateAsync(payload)
                onClose()
                navigate(`/projects/${created.id}`)
            }
        } catch (error) {
            if (!applyFieldErrors(error, setError)) setFormError(getErrorMessage(error))
        }
    })

    return (
        <form onSubmit={onSubmit} noValidate className="space-y-4">
            {formError && (
                <p role="alert" className="rounded-2xl bg-red-50 px-3 py-2 text-sm text-red-600">
                    {formError}
                </p>
            )}

            <TextField
                label="Project name"
                type="text"
                placeholder="Project name"
                error={errors.name?.message}
                {...register('name')}
            />
            <TextAreaField
                label="Description"
                placeholder="Description (optional)"
                error={errors.description?.message}
                {...register('description')}
            />

            <div className="flex justify-end gap-3 pt-2">
                <Button type="button" variant="secondary" fullWidth={false} onClick={onClose} disabled={isSubmitting}>
                    Cancel
                </Button>
                <Button type="submit" fullWidth={false} loading={isSubmitting}>
                    {project ? 'Save changes' : 'Create project'}
                </Button>
            </div>
        </form>
    )
}