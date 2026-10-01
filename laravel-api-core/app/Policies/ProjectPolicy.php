<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $project->projectUsers()->where('user_id', $user->id)->exists();
    }

    // semua user login boleh bikin project baru
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Project $project): bool
    {
        return $project->projectUsers()
            ->where('user_id', $user->id)
            ->where('role', 'owner')
            ->exists();
    }

    // aturan sama: owner only
    public function delete(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }
}
