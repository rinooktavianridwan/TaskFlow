import type { ReactNode } from 'react'

type PageFrameProps = {
    title: string
    description: string
    children?: ReactNode
}

export function PageFrame({ title, description, children }: PageFrameProps) {
    return (
        <section className="mx-auto w-full max-w-6xl px-5 py-8">
            <header className="mb-6">
                <p className="mb-2 text-sm font-semibold uppercase tracking-wider text-emerald-700">
                    TaskFlow
                </p>
                <h1 className="text-3xl font-bold text-slate-900">{title}</h1>
                <p className="mt-2 text-slate-600">{description}</p>
            </header>

            {children ?? (
                <div className="rounded-xl border border-slate-200 bg-white p-6 text-slate-600">
                    Halaman siap dihubungkan ke API.
                </div>
            )}
        </section>
    )
}