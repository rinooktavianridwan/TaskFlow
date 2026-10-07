<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Enums\InvitationStatus;
use App\Enums\ProjectRole;
use App\Jobs\SendInvitationEmailJob;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\ProjectUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class InvitationService
{
    public function __construct(
        protected ActivityLogger $logger,
    ) {
    }

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
    public function create(Project $project, string $email, string $role, User $actor): ProjectInvitation
    {
        return DB::transaction(function () use ($project, $email, $role, $actor) {
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

            $this->logger->record(
                $project->id,
                $actor,
                ActivityAction::InvitationSent,
                "Invitation sent to {$invitation->email} as {$invitation->role}.",
                null,
                $this->metadata($invitation),
            );

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
    public function delete(Project $project, ProjectInvitation $invitation, User $actor): void
    {
        DB::transaction(function () use ($project, $invitation, $actor) {
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

            $this->logger->record(
                $project->id,
                $actor,
                ActivityAction::InvitationRevoked,
                "Invitation to {$lockedInvitation->email} revoked.",
                null,
                $this->metadata($lockedInvitation),
            );

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

            $this->logger->record(
                $lockedInvitation->project_id,
                $user,
                ActivityAction::InvitationAccepted,
                "Invitation accepted by {$lockedInvitation->email}.",
                null,
                $this->metadata($lockedInvitation),
            );
        });
    }

    /**
     * @throws Throwable
     */
    public function decline(ProjectInvitation $invitation, User $user): void
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

            $lockedInvitation->update(['status' => InvitationStatus::Declined->value]);

            $this->logger->record(
                $lockedInvitation->project_id,
                $user,
                ActivityAction::InvitationDeclined,
                "Invitation declined by {$lockedInvitation->email}.",
                null,
                $this->metadata($lockedInvitation),
            );
        });
    }

    /**
     * Snapshot undangan untuk log. Token sengaja tidak disertakan.
     *
     * @return array<string, mixed>
     */
    private function metadata(ProjectInvitation $invitation): array
    {
        return [
            'invitation_id' => $invitation->id,
            'email'         => $invitation->email,
            'role'          => $invitation->role,
        ];
    }
}
