<?php

namespace App\Services;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectUser;
use App\Models\User;
use App\Enums\TaskActivityAction;
use App\Jobs\SyncTaskReminderJob;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class MemberService
{
    public function index(Project $project, array $filter): Builder
    {
        $keyword = $filter['name'] ?? null;

        return ProjectUser::query()
            ->where('project_id', $project->id)
            ->with('user')
            ->when($keyword, function (Builder $query, string $value) {
                $query->whereHas('user', function (Builder $userQuery) use ($value) {
                    $userQuery->where('name', 'like', "%$value%");
                });
            })
            ->orderBy('id');
    }

    /**
     * @throws Throwable
     */
    public function updateRole(Project $project, User $targetUser, string $newRole): ProjectUser
    {
        return DB::transaction(function () use ($project, $targetUser, $newRole) {
            $project->lockRow();

            $membership = $project->projectUsers()
                ->where('user_id', $targetUser->id)
                ->firstOrFail();

            if ($membership->role === ProjectRole::Owner->value && $newRole !== ProjectRole::Owner->value) {
                $this->ensureNotLastOwner($project);
            }

            $membership->update(['role' => $newRole]);

            return $membership->fresh('user');
        });
    }

    /**
     * @throws Throwable
     */
    public function remove(Project $project, User $targetUser, User $actor): bool
    {
        return DB::transaction(function () use ($project, $targetUser, $actor) {
            $project->lockRow();

            $membership = $project->projectUsers()
                ->where('user_id', $targetUser->id)
                ->firstOrFail();

            if ($membership->role === ProjectRole::Owner->value) {
                $this->ensureNotLastOwner($project);
            }

            $assignedTasks = $project->tasks()
                ->where('assigned_to', $targetUser->id)
                ->lockForUpdate()
                ->get();

            $description = $actor->is($targetUser)
                ? 'Task unassigned because the assignee left the project.'
                : 'Task unassigned because the assignee was removed from the project.';

            foreach ($assignedTasks as $task) {
                $task->update(['assigned_to' => null]);

                $task->taskActivities()->create([
                    'user_id'     => $actor->id,
                    'action'      => TaskActivityAction::Assigned->value,
                    'description' => $description,
                ]);

                if ($task->due_date !== null) {
                    SyncTaskReminderJob::dispatch($task->id)->afterCommit();
                }
            }

            return $membership->delete();
        });
    }

    private function ensureNotLastOwner(Project $project): void
    {
        $ownerCount = $project->projectUsers()
            ->where('role', ProjectRole::Owner->value)
            ->count();

        if ($ownerCount <= 1) {
            throw ValidationException::withMessages([
                'role' => ['A project must always have at least one owner.'],
            ]);
        }
    }
}
