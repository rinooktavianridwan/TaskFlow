<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\TaskChecklistItem;
use App\Models\User;

class TaskChecklistItemPolicy
{
    public function view(User $user, TaskChecklistItem $item): bool
    {
        return $item->task->project->hasMember($user);
    }

    public function delete(User $user, TaskChecklistItem $item): bool
    {
        return $item->task->project->hasRole($user, ProjectRole::Owner, ProjectRole::Editor);
    }
}
