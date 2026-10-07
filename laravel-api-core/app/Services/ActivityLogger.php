<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\User;

class ActivityLogger
{
    /**
     * Mencatat satu event. Panggil di dalam transaction Service yang sama dengan
     * perubahan datanya, supaya log dan data selalu konsisten.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        int $projectId,
        ?User $actor,
        ActivityAction $action,
        string $description,
        ?int $taskId = null,
        array $metadata = [],
    ): ActivityLog {
        return ActivityLog::create([
            'project_id'  => $projectId,
            'actor_id'    => $actor?->id,
            'task_id'     => $taskId,
            'action'      => $action->value,
            'description' => $description,
            'metadata'    => $metadata === [] ? null : $metadata,
        ]);
    }

    /**
     * Event pada sebuah task. Judul task disimpan sebagai snapshot supaya log
     * tetap terbaca setelah task diubah atau dihapus.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function forTask(
        Task $task,
        ?User $actor,
        ActivityAction $action,
        string $description,
        array $metadata = [],
    ): ActivityLog {
        return $this->record(
            $task->project_id,
            $actor,
            $action,
            $description,
            $task->id,
            ['task_title' => $task->title] + $metadata,
        );
    }
}
