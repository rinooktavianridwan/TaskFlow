<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DailySummaryService
{
    /**
     * Rangkuman aktivitas milik $user untuk rentang tanggal (dalam zona waktu user),
     * dikelompokkan per hari, per project, lalu per task.
     *
     * @return array<string, mixed>
     */
    public function forUser(User $user, string $from, string $to): array
    {
        $timezone = $user->timezone ?: 'UTC';

        $start = Carbon::createFromFormat('Y-m-d', $from, $timezone)->startOfDay()->utc();
        $end   = Carbon::createFromFormat('Y-m-d', $to, $timezone)->endOfDay()->utc();

        $logs = ActivityLog::query()
            ->where('actor_id', $user->id)
            ->whereBetween('created_at', [$start, $end])
            ->with('project:id,name')
            ->orderBy('id')
            ->get();

        $tasks = Task::query()
            ->whereIn('id', $logs->pluck('task_id')->filter()->unique()->values())
            ->withChecklistCounts()
            ->get()
            ->keyBy('id');

        $days = $logs
            ->groupBy(fn(ActivityLog $log) => $log->created_at->copy()->setTimezone($timezone)->toDateString())
            ->sortKeysDesc()
            ->map(fn(Collection $dayLogs, string $date) => $this->buildDay($date, $dayLogs, $tasks))
            ->values()
            ->all();

        return [
            'timezone' => $timezone,
            'from'     => $from,
            'to'       => $to,
            'days'     => $days,
        ];
    }

    /**
     * @param  Collection<int, ActivityLog>  $dayLogs
     * @param  Collection<int, Task>         $tasks
     *
     * @return array<string, mixed>
     */
    private function buildDay(string $date, Collection $dayLogs, Collection $tasks): array
    {
        $projects = $dayLogs
            ->groupBy('project_id')
            ->map(function (Collection $projectLogs) use ($tasks) {
                /** @var ActivityLog $first */
                $first = $projectLogs->first();

                return [
                    'project_id'   => $first->project_id,
                    'project_name' => $first->project?->name,
                    'events'       => $projectLogs
                        ->whereNull('task_id')
                        ->map(fn(ActivityLog $log) => $this->event($log))
                        ->values()
                        ->all(),
                    'tasks'        => $projectLogs
                        ->whereNotNull('task_id')
                        ->groupBy('task_id')
                        ->map(fn(Collection $taskLogs, $taskId) => $this->buildTask((int)$taskId, $taskLogs, $tasks))
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        return [
            'date'         => $date,
            'total_events' => $dayLogs->count(),
            'projects'     => $projects,
        ];
    }

    /**
     * @param  Collection<int, ActivityLog>  $taskLogs
     * @param  Collection<int, Task>         $tasks
     *
     * @return array<string, mixed>
     */
    private function buildTask(int $taskId, Collection $taskLogs, Collection $tasks): array
    {
        $task = $tasks->get($taskId);

        return [
            'task_id'         => $taskId,
            'task_title'      => $task?->title ?? ($taskLogs->last()->metadata['task_title'] ?? null),
            'task_exists'     => $task !== null,
            // Kondisi checklist saat ini (bukan hanya hari itu); null bila task sudah dihapus.
            'checklist'       => $task
                ? ['total' => (int)$task->checklist_items_count, 'done' => (int)$task->checklist_done_count]
                : null,
            'completed_items' => $this->completedItems($taskLogs),
            'events'          => $taskLogs->map(fn(ActivityLog $log) => $this->event($log))->values()->all(),
        ];
    }

    /**
     * Judul item yang pada akhir hari itu berstatus selesai oleh user. Item yang dicentang
     * lalu dibuka kembali atau dihapus pada hari yang sama tidak dihitung.
     *
     * @param  Collection<int, ActivityLog>  $taskLogs
     *
     * @return array<int, string>
     */
    private function completedItems(Collection $taskLogs): array
    {
        $completed = [];

        foreach ($taskLogs as $log) {
            $itemId = $log->metadata['item_id'] ?? null;

            if ($itemId === null) {
                continue;
            }

            if ($log->action === ActivityAction::ChecklistItemCompleted->value) {
                $completed[$itemId] = $log->metadata['item_title'] ?? '';
            } elseif (in_array($log->action, [
                ActivityAction::ChecklistItemReopened->value,
                ActivityAction::ChecklistItemRemoved->value,
            ], true)) {
                unset($completed[$itemId]);
            }
        }

        return array_values($completed);
    }

    /**
     * @return array<string, mixed>
     */
    private function event(ActivityLog $log): array
    {
        return [
            'id'          => $log->id,
            'action'      => $log->action,
            'description' => $log->description,
            'metadata'    => $log->metadata,
            'created_at'  => $log->created_at,
        ];
    }
}
