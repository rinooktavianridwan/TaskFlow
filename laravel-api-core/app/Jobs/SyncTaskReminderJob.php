<?php

namespace App\Jobs;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\Grpc\NotificationGrpcClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncTaskReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        public int $taskId,
    ) {
    }

    public function handle(NotificationGrpcClient $client): void
    {
        $task = Task::query()
            ->with(['project', 'assignee'])
            ->find($this->taskId);

        if (
            $task === null
            || $task->assignee === null
            || $task->project === null
            || $task->due_date === null
            || $task->status === TaskStatus::Done->value
        ) {
            $client->cancelTaskReminder($this->taskId);

            return;
        }

        $dueDate = $task->due_date
            ->copy()
            ->utc()
            ->format('Y-m-d\TH:i:s\Z');

        $client->scheduleTaskReminder(
            taskId: $task->id,
            taskTitle: $task->title,
            projectName: $task->project->name,
            assigneeEmail: $task->assignee->email,
            dueDate: $dueDate,
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Gagal menyinkronkan reminder task setelah semua percobaan', [
            'task_id' => $this->taskId,
            'error'   => $exception->getMessage(),
        ]);
    }
}
