<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Enums\ProjectRole;
use App\Jobs\SyncTaskReminderJob;
use App\Models\Project;
use App\Models\ProjectUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class MemberService
{
    public function __construct(
        protected ActivityLogger $logger,
    ) {
    }

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
    public function updateRole(Project $project, User $targetUser, string $newRole, User $actor): ProjectUser
    {
        return DB::transaction(function () use ($project, $targetUser, $newRole, $actor) {
            $project->lockRow();

            $membership = $project->projectUsers()
                ->where('user_id', $targetUser->id)
                ->firstOrFail();

            $previousRole = $membership->role;

            if ($previousRole === ProjectRole::Owner->value && $newRole !== ProjectRole::Owner->value) {
                $this->ensureNotLastOwner($project);
            }

            $membership->update(['role' => $newRole]);

            if ($membership->wasChanged('role')) {
                $this->logger->record(
                    $project->id,
                    $actor,
                    ActivityAction::MemberRoleChanged,
                    "{$targetUser->name}'s role changed from {$previousRole} to {$newRole}.",
                    null,
                    [
                        'user_id'   => $targetUser->id,
                        'user_name' => $targetUser->name,
                        'from'      => $previousRole,
                        'to'        => $newRole,
                    ],
                );
            }

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

            $left = $actor->is($targetUser);

            $description = $left
                ? 'Task unassigned because the assignee left the project.'
                : 'Task unassigned because the assignee was removed from the project.';

            foreach ($assignedTasks as $task) {
                $task->update(['assigned_to' => null]);

                $this->logger->forTask($task, $actor, ActivityAction::Assigned, $description, [
                    'previous_assignee_id'   => $targetUser->id,
                    'previous_assignee_name' => $targetUser->name,
                ]);

                if ($task->due_date !== null) {
                    SyncTaskReminderJob::dispatch($task->id)->afterCommit();
                }
            }

            $this->logger->record(
                $project->id,
                $actor,
                $left ? ActivityAction::MemberLeft : ActivityAction::MemberRemoved,
                $left ? "{$targetUser->name} left the project." : "{$targetUser->name} removed from the project.",
                null,
                [
                    'user_id'    => $targetUser->id,
                    'user_name'  => $targetUser->name,
                    'user_email' => $targetUser->email,
                    'role'       => $membership->role,
                ],
            );

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
