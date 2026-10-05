<?php

namespace App\Services;

use App\Enums\InvitationStatus;
use App\Enums\ProjectRole;
use App\Jobs\SendInvitationEmailJob;
use App\Models\Project;
use App\Models\ProjectInvitation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;
use App\Models\ProjectUser;
use App\Models\User;

class InvitationService
{
    public function index(Project $project, array $filter): Builder
    {
        return ProjectInvitation::query()
            ->where('project_id', $project->id)
            ->when($filter['status'] ?? null, function (Builder $query, string $status) {
                $query->where('status', $status);
            })
            ->orderByDesc('id');
    }

    public function receivedBy(User $user): Builder
    {
        return ProjectInvitation::query()
            ->where('email', $user->email)
            ->where('status', InvitationStatus::Pending->value)
            ->where(function (Builder $query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with('project')
            ->orderByDesc('id');
    }

    /**
     * @throws Throwable
     */
    public function create(Project $project, string $email, string $role): ProjectInvitation
    {
        return DB::transaction(function () use ($project, $email, $role) {
            $project->lockRow();
            if ($project->projectUsers()
                ->whereHas('user', function (Builder $query) use ($email) {
                    $query->where('email', $email);
                })->exists()) {
                throw ValidationException::withMessages([
                    'email' => ['This user is already a member of the project.'],
                ]);
            }

            if ($project->invitations()
                ->where('email', $email)
                ->where('status', InvitationStatus::Pending->value)
                ->where('expires_at', '>', now())
                ->exists()
            ) {
                throw ValidationException::withMessages([
                    'email' => ['A pending invitation already exists for this email address.'],
                ]);
            }

            $invitation = $project->invitations()->create([
                'email'      => $email,
                'role'       => ProjectRole::from($role)->value,
                'token'      => Str::random(64),
                'status'     => InvitationStatus::Pending->value,
                'expires_at' => now()->addDays(7),
            ]);

            SendInvitationEmailJob::dispatch(
                $project->id,
                $project->name,
                $invitation->email,
                $invitation->token,
            )->afterCommit();

            return $invitation;
        });
    }

    /**
     * @throws Throwable
     */
    public function delete(Project $project, ProjectInvitation $invitation): void
    {
        DB::transaction(function () use ($project, $invitation) {
            // Dikunci agar tidak berbalapan dengan accept/decline yang juga mengunci baris ini.
            $lockedInvitation = $project->invitations()
                ->whereKey($invitation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInvitation->status !== InvitationStatus::Pending->value) {
                throw ValidationException::withMessages([
                    'invitation' => ['Only pending invitations can be revoked.'],
                ]);
            }

            $lockedInvitation->delete();
        });
    }

    /**
     * @throws Throwable
     */
    public function accept(ProjectInvitation $invitation, User $user): void
    {
        DB::transaction(function () use ($invitation, $user) {
            $lockedInvitation = ProjectInvitation::query()
                ->whereKey($invitation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInvitation->status !== InvitationStatus::Pending->value) {
                throw ValidationException::withMessages([
                    'invitation' => ['This invitation is no longer pending.'],
                ]);
            }

            if ($lockedInvitation->expires_at?->isPast()) {
                throw ValidationException::withMessages([
                    'invitation' => ['This invitation has expired.'],
                ]);
            }

            $alreadyMember = ProjectUser::query()
                ->where('project_id', $lockedInvitation->project_id)
                ->where('user_id', $user->id)
                ->exists();

            if ($alreadyMember) {
                throw ValidationException::withMessages([
                    'invitation' => ['You are already a member of this project.'],
                ]);
            }

            $lockedInvitation->project->projectUsers()->create([
                'user_id' => $user->id,
                'role'    => ProjectRole::from($lockedInvitation->role)->value,
            ]);

            $lockedInvitation->update(['status' => InvitationStatus::Accepted->value]);
        });
    }

    /**
     * @throws Throwable
     */
    public function decline(ProjectInvitation $invitation): void
    {
        DB::transaction(function () use ($invitation) {
            $lockedInvitation = ProjectInvitation::query()
                ->whereKey($invitation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInvitation->status !== InvitationStatus::Pending->value) {
                throw ValidationException::withMessages([
                    'invitation' => ['This invitation is no longer pending.'],
                ]);
            }

            if ($lockedInvitation->expires_at?->isPast()) {
                throw ValidationException::withMessages([
                    'invitation' => ['This invitation has expired.'],
                ]);
            }

            $lockedInvitation->update(['status' => InvitationStatus::Declined->value]);
        });
    }
}
