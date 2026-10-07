<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use App\Jobs\SyncTaskReminderJob;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class TaskChecklistService
{
    private const MAX_ITEMS = 50;

    public function __construct(
        protected ActivityLogger $logger,
    ) {
    }

    /**
     * @return array{0: TaskChecklistItem, 1: Task}
     *
     * @throws Throwable
     */
    public function create(Task $task, string $title, User $actor): array
    {
        return DB::transaction(function () use ($task, $title, $actor) {
            $task = $this->lockTask($task->id);

            if ($task->checklistItems()->count() >= self::MAX_ITEMS) {
                throw ValidationException::withMessages([
                    'title' => ['A task can have at most ' . self::MAX_ITEMS . ' checklist items.'],
                ]);
            }

            $item = $task->checklistItems()->create([
                'title'    => $title,
                'is_done'  => false,
                'position' => (int)($task->checklistItems()->max('position') ?? 0) + 1,
            ]);

            $this->logger->forTask(
                $task,
                $actor,
                ActivityAction::ChecklistItemAdded,
                "Checklist item \"{$item->title}\" added.",
                ['item_id' => $item->id, 'item_title' => $item->title],
            );

            // Item baru yang belum selesai membuka kembali task yang sudah done.
            $this->syncStatus($task, $actor, reopen: true);

            return [$item, $task->load('assignee')];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @return array{0: TaskChecklistItem, 1: Task}
     *
     * @throws Throwable
     */
    public function update(TaskChecklistItem $item, User $actor, array $data): array
    {
        return DB::transaction(function () use ($item, $actor, $data) {
            $task = $this->lockTask($item->task_id);
            $item = $task->checklistItems()->whereKey($item->id)->firstOrFail();

            $this->ensureCanUpdate($task, $actor, $data);

            $item->update($data);

            if ($item->wasChanged('is_done')) {
                $this->logger->forTask(
                    $task,
                    $actor,
                    $item->is_done ? ActivityAction::ChecklistItemCompleted : ActivityAction::ChecklistItemReopened,
                    $item->is_done
                        ? "Checklist item \"{$item->title}\" completed."
                        : "Checklist item \"{$item->title}\" reopened.",
                    ['item_id' => $item->id, 'item_title' => $item->title],
                );

                $this->syncStatus($task, $actor, reopen: !$item->is_done);
            }

            return [$item, $task->load('assignee')];
        });
    }

    /**
     * @throws Throwable
     */
    public function delete(TaskChecklistItem $item, User $actor): void
    {
        DB::transaction(function () use ($item, $actor) {
            $task = $this->lockTask($item->task_id);
            $item = $task->checklistItems()->whereKey($item->id)->firstOrFail();

            $this->logger->forTask(
                $task,
                $actor,
                ActivityAction::ChecklistItemRemoved,
                "Checklist item \"{$item->title}\" removed.",
                ['item_id' => $item->id, 'item_title' => $item->title, 'was_done' => $item->is_done],
            );

            $item->delete();

            // Menghapus item terakhir yang belum selesai bisa membuat semua item tuntas.
            $this->syncStatus($task, $actor, reopen: false);
        });
    }

    /**
     * Task dikunci supaya dua toggle bersamaan menghitung ulang status secara berurutan.
     */
    private function lockTask(int $taskId): Task
    {
        return Task::query()->whereKey($taskId)->lockForUpdate()->firstOrFail();
    }

    /**
     * Owner/editor boleh mengubah semua field. Member lain hanya boleh mencentang
     * item pada task yang ditugaskan kepadanya.
     *
     * @param  array<string, mixed>  $data
     */
    private function ensureCanUpdate(Task $task, User $actor, array $data): void
    {
        $project = $task->project;

        if (!$project->hasMember($actor)) {
            throw new AuthorizationException('You are not a member of this project.');
        }

        if ($project->hasRole($actor, ProjectRole::Owner, ProjectRole::Editor)) {
            return;
        }

        $isAssignedToActor     = $task->assigned_to === $actor->id;
        $onlyDoneFlagSubmitted = array_keys($data) === ['is_done'];

        if (!$isAssignedToActor || !$onlyDoneFlagSubmitted) {
            throw new AuthorizationException('You may only check items of your assigned task.');
        }
    }

    private function syncStatus(Task $task, User $actor, bool $reopen): void
    {
        $total = $task->checklistItems()->count();

        if ($total === 0) {
            return;
        }

        $done = $task->checklistItems()->where('is_done', true)->count();

        $target = match (true) {
            $done === $total                                       => TaskStatus::Done,
            $reopen && $task->status === TaskStatus::Done->value   => TaskStatus::InProgress,
            $done > 0 && $task->status === TaskStatus::Todo->value => TaskStatus::InProgress,
            default                                                => null,
        };

        if ($target === null || $target->value === $task->status) {
            return;
        }

        $previous = $task->status;

        $task->update(['status' => $target->value]);

        $this->logger->forTask(
            $task,
            $actor,
            ActivityAction::StatusChanged,
            "Status changed from {$previous} to {$task->status} (via checklist).",
            ['from' => $previous, 'to' => $task->status, 'via' => 'checklist'],
        );

        if ($task->due_date !== null && $task->assigned_to !== null) {
            SyncTaskReminderJob::dispatch($task->id)->afterCommit();
        }
    }
}
