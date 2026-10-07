import { useState } from 'react'
import { EmptyState, ErrorState, LoadingState } from '@/components/feedback/states'
import { Button } from '@/components/ui/Button'
import { TextField } from '@/components/ui/TextField'
import { getErrorMessage } from '@/lib/form-errors'
import { useUrlParams } from '@/lib/use-url-params'
import { DaySummaryCard } from '../components/DaySummaryCard'
import { formatSummaryRange } from '../format'
import { useDailySummary } from '../queries'
import { normalizeDateParam, resolveRange } from '../range'

export function DailySummaryPage() {
    const [params, updateParams] = useUrlParams()
    // Input tanggal tidak dikontrol (agar tidak terhapus saat user masih mengetik); key dinaikkan untuk mengosongkannya.
    const [inputKeys, setInputKeys] = useState({ from: 0, to: 0 })
    const from = normalizeDateParam(params.get('from'))
    const to = normalizeDateParam(params.get('to'))
    const { range, error: rangeError } = resolveRange(from, to)

    const { data, isPending, isError, error, refetch, isPlaceholderData } = useDailySummary(
        range,
        rangeError === null,
    )

    function handleFromChange(value: string) {
        // Input tanggal melaporkan nilai setengah jadi saat mengetik: tunggu sampai valid atau kosong.
        if (value !== '' && normalizeDateParam(value) === '') return
        if (value === '') {
            updateParams({ from: null, to: null })
            setInputKeys((current) => ({ ...current, to: current.to + 1 }))
            return
        }
        updateParams({ from: value })
    }

    function handleToChange(value: string) {
        if (value !== '' && normalizeDateParam(value) === '') return
        updateParams({ to: value || null })
    }

    function handleToday() {
        updateParams({ from: null, to: null })
        setInputKeys((current) => ({ from: current.from + 1, to: current.to + 1 }))
    }

    function renderContent() {
        // Query yang dinonaktifkan berstatus "pending" selamanya: cek rentang tidak valid lebih dulu.
        if (rangeError) return null
        if (isPending) return <LoadingState />
        if (isError) return <ErrorState message={getErrorMessage(error)} onRetry={() => void refetch()} />

        return (
            <div className={isPlaceholderData ? 'opacity-60 transition-opacity' : 'transition-opacity'}>
                <p className="text-sm text-gray-500">
                    {formatSummaryRange(data.from, data.to)} · Times shown in {data.timezone}
                </p>
                {data.days.length === 0 ? (
                    <div className="mt-4">
                        <EmptyState
                            title="No activity"
                            description="You have no recorded activity in this period."
                        />
                    </div>
                ) : (
                    <div className="mt-4 space-y-4">
                        {data.days.map((day) => (
                            <DaySummaryCard key={day.date} day={day} timezone={data.timezone} />
                        ))}
                    </div>
                )}
            </div>
        )
    }

    return (
        <div>
            <h1 className="text-2xl font-bold text-gray-800">Daily summary</h1>
            <p className="mt-1 text-sm text-gray-600">What you worked on, grouped by project and task.</p>

            <div className="mt-4 flex flex-wrap items-end gap-3">
                <div className="w-44">
                    <TextField
                        key={`from-${inputKeys.from}`}
                        label="From"
                        showLabel
                        type="date"
                        defaultValue={from}
                        onChange={(event) => handleFromChange(event.target.value)}
                    />
                </div>
                <div className="w-44">
                    <TextField
                        key={`to-${inputKeys.to}`}
                        label="To"
                        showLabel
                        type="date"
                        defaultValue={to}
                        min={from || undefined}
                        disabled={from === ''}
                        onChange={(event) => handleToChange(event.target.value)}
                    />
                </div>
                {from !== '' && (
                    <Button type="button" variant="secondary" fullWidth={false} onClick={handleToday}>
                        Today
                    </Button>
                )}
            </div>

            {rangeError && (
                <p role="alert" className="mt-4 rounded-2xl bg-red-50 px-3 py-2 text-sm text-red-600">
                    {rangeError}
                </p>
            )}

            <div className="mt-6">{renderContent()}</div>
        </div>
    )
}