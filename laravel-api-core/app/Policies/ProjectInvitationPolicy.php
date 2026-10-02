<?php

namespace App\Policies;

use App\Models\ProjectInvitation;
use App\Models\User;

class ProjectInvitationPolicy
{
    public function accept(User $user, ProjectInvitation $invitation): bool
    {
        return $user->email === $invitation->email;
    }

    public function decline(User $user, ProjectInvitation $invitation): bool
    {
        return $this->accept($user, $invitation);
    }
}
