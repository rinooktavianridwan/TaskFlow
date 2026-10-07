<?php

use App\Enums\ActivityAction;
use App\Enums\InvitationStatus;
use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\ProjectUser;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->beforeEach(function () {
    Queue::fake();
})->in('Feature');

/*
|--------------------------------------------------------------------------
| Helper menyiapkan data
|--------------------------------------------------------------------------
*/

/** Buat project baru, $owner otomatis jadi owner. */
function createProject(User $owner, array $attributes = []): Project
{
    $project = Project::create(array_merge([
        'name'        => 'Test Project',
        'description' => null,
    ], $attributes));

    $project->projectUsers()->create([
        'user_id' => $owner->id,
        'role'    => ProjectRole::Owner->value,
    ]);

    return $project;
}

/** Tambahkan user ke project dengan role tertentu. */
function addMember(Project $project, User $user, ProjectRole $role): ProjectUser
{
    return $project->projectUsers()->create([
        'user_id' => $user->id,
        'role'    => $role->value,
    ]);
}

/** Buat undangan. Default: pending, role editor, berlaku 7 hari. */
function createInvitation(Project $project, string $email, array $overrides = []): ProjectInvitation
{
    return $project->invitations()->create(array_merge([
        'email'      => $email,
        'role'       => ProjectRole::Editor->value,
        'token'      => Str::random(64),
        'status'     => InvitationStatus::Pending->value,
        'expires_at' => now()->addDays(7),
    ], $overrides));
}

/** Buat task. Default: todo, tanpa assignee, tanpa due date. */
function createTask(Project $project, array $attributes = []): Task
{
    return $project->tasks()->create(array_merge([
        'title'       => 'Task test',
        'description' => null,
        'assigned_to' => null,
        'status'      => TaskStatus::Todo->value,
        'due_date'    => null,
    ], $attributes));
}

/** Buat catatan aktivitas pada task. $user boleh null (aktivitas tanpa user). */
function createTaskActivity(Task $task, ?User $user, array $attributes = []): ActivityLog
{
    return ActivityLog::create(array_merge([
        'project_id'  => $task->project_id,
        'task_id'     => $task->id,
        'actor_id'    => $user?->id,
        'action'      => ActivityAction::Updated->value,
        'description' => 'Task details updated.',
    ], $attributes));
}

/** Buat log aktivitas tingkat project (tanpa task). */
function createProjectActivity(Project $project, ?User $actor, ActivityAction $action, array $attributes = []): ActivityLog
{
    return ActivityLog::create(array_merge([
        'project_id'  => $project->id,
        'actor_id'    => $actor?->id,
        'task_id'     => null,
        'action'      => $action->value,
        'description' => 'Aktivitas project.',
    ], $attributes));
}

/** Buat item checklist pada task. Default: belum selesai. */
function createChecklistItem(Task $task, array $attributes = []): TaskChecklistItem
{
    return $task->checklistItems()->create(array_merge([
        'title'    => 'Item test',
        'is_done'  => false,
        'position' => 1,
    ], $attributes));
}
