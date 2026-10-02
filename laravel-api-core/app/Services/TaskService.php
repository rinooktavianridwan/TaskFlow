<?php

namespace App\Services;

use App\DTOs\CreateTaskData;
use App\Enums\ProjectRole;
use App\Enums\TaskActivityAction;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectUser;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class TaskService
{
    public function index(Project $project, array $filter): Builder
    {
        return Task::query()
            ->where('project_id', $project->id)
            ->with('assignee')
            ->when($filter['title'] ?? null, function (Builder $query, string $title) {
                $query->where('title', 'like', "%$title%");
            })
            ->when($filter['status'] ?? null, function (Builder $query, string $status) {
                $query->where('status', $status);
            })
            ->orderByDesc('id');
    }

    /**
     * @throws Throwable
     */
    public function create(Project $project, CreateTaskData $data, User $creator): Task
    {
        return DB::transaction(function () use ($project, $data, $creator) {
            if ($data->assignedTo !== null) {
                $project->lockRow();
                $this->ensureAssigneeIsProjectMember($project, $data->assignedTo);
            }

            $task = $project->tasks()->create([
                'title'       => $data->title,
                'description' => $data->description,
                'assigned_to' => $data->assignedTo,
                'status'      => TaskStatus::Todo->value,
                'due_date'    => $data->dueDate,
            ]);

            $this->logActivity($task, $creator, TaskActivityAction::Created, 'Task created.');

            return $task->load('assignee');
        });
    }

    public function show(Task $task): Task
    {
        return $task->load('assignee');
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws Throwable
     */
    public function update(Task $task, User $actor, array $data): Task
    {
        return DB::transaction(function () use ($task, $actor, $data) {
            $project    = $task->project;
            $assigneeId = $data['assigned_to'] ?? null;

            if ($assigneeId !== null) {
                $project->lockRow();
            }

            $task = Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

            $this->ensureCanUpdate($project, $task, $actor, $data);
            $this->ensureAssigneeIsProjectMember($project, $assigneeId);

            $previousStatus = $task->status;

            $task->update($data);
            $task->load('assignee');

            if ($task->wasChanged('status')) {
                $this->logActivity(
                    $task,
                    $actor,
                    TaskActivityAction::StatusChanged,
                    "Status changed from {$previousStatus} to {$task->status}.",
                );
            }

            if ($task->wasChanged('assigned_to')) {
                $this->logActivity(
                    $task,
                    $actor,
                    TaskActivityAction::Assigned,
                    $task->assignee ? "Task assigned to {$task->assignee->name}." : 'Task unassigned.',
                );
            }

            if ($task->wasChanged(['title', 'description', 'due_date'])) {
                $this->logActivity($task, $actor, TaskActivityAction::Updated, 'Task details updated.');
            }

            return $task;
        });
    }

    public function delete(Task $task): bool
    {
        return $task->delete();
    }

    /**
     * Owner/editor boleh mengubah semua field. Member lain hanya boleh mengubah
     * `status` pada task yang ditugaskan kepadanya.
     *
     * @param  array<string, mixed>  $data
     */
    private function ensureCanUpdate(Project $project, Task $task, User $actor, array $data): void
    {
        if (! $project->hasMember($actor)) {
            throw new AuthorizationException('You are not a member of this project.');
        }

        if ($project->hasRole($actor, ProjectRole::Owner, ProjectRole::Editor)) {
            return;
        }

        $isAssignedToActor      = $task->assigned_to === $actor->id;
        $onlyStatusWasSubmitted = array_keys($data) === ['status'];

        if (! $isAssignedToActor || ! $onlyStatusWasSubmitted) {
            throw new AuthorizationException('You may only update the status of your assigned task.');
        }
    }

    private function ensureAssigneeIsProjectMember(Project $project, ?int $assigneeId): void
    {
        if ($assigneeId === null) {
            return;
        }

        $isProjectMember = ProjectUser::query()
            ->where('project_id', $project->id)
            ->where('user_id', $assigneeId)
            ->exists();

        if (! $isProjectMember) {
            throw ValidationException::withMessages([
                'assigned_to' => ['The assignee must be a member of this project.'],
            ]);
        }
    }

    private function logActivity(Task $task, User $actor, TaskActivityAction $action, string $description): void
    {
        $task->taskActivities()->create([
            'user_id'     => $actor->id,
            'action'      => $action->value,
            'description' => $description,
        ]);
    }
}
