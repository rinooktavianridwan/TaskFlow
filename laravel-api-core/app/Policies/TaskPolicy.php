<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        return $task->project->hasMember($user);
    }

    public function delete(User $user, Task $task): bool
    {
        return $task->project->hasRole($user, ProjectRole::Owner, ProjectRole::Editor);
    }

    public function manageChecklist(User $user, Task $task): bool
    {
        return $task->project->hasRole($user, ProjectRole::Owner, ProjectRole::Editor);
    }
}
