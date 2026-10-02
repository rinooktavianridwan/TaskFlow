<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $project->hasMember($user);
    }

    // semua user login boleh bikin project baru
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Project $project): bool
    {
        return $project->hasRole($user, ProjectRole::Owner);
    }

    // aturan sama: owner only
    public function delete(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    public function createTask(User $user, Project $project): bool
    {
        return $project->hasRole($user, ProjectRole::Owner, ProjectRole::Editor);
    }

    public function removeMember(User $user, Project $project, User $targetUser): bool
    {
        return $this->update($user, $project) || $user->id === $targetUser->id;
    }
}
