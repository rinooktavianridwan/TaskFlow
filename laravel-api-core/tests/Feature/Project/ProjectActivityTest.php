<?php

use App\Enums\ActivityAction;
use App\Enums\ProjectRole;
use App\Models\ActivityLog;
use App\Models\User;

// ---------------------------------------------------------------- TIMELINE

test('owner bisa melihat timeline project, terbaru di atas, lengkap dengan aktor', function () {
    $owner   = User::factory()->create(['name' => 'Pemilik']);
    $project = createProject($owner);

    $first  = createProjectActivity($project, $owner, ActivityAction::InvitationSent, [
        'description' => 'Invitation sent to a@example.com as editor.',
        'metadata'    => ['email' => 'a@example.com'],
    ]);
    $second = createProjectActivity($project, $owner, ActivityAction::ProjectUpdated);

    $this->actingAs($owner)
        ->getJson("/api/projects/{$project->id}/activities")
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items.0.id', $second->id)
        ->assertJsonPath('data.items.0.actor.name', 'Pemilik')
        ->assertJsonPath('data.items.1.id', $first->id)
        ->assertJsonPath('data.items.1.metadata.email', 'a@example.com')
        ->assertJsonPath('data.meta.total', 2);
});

test('editor, viewer, dan non-member tidak bisa melihat timeline project', function () {
    $owner    = User::factory()->create();
    $editor   = User::factory()->create();
    $viewer   = User::factory()->create();
    $outsider = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $editor, ProjectRole::Editor);
    addMember($project, $viewer, ProjectRole::Viewer);

    foreach ([$editor, $viewer, $outsider] as $user) {
        $this->actingAs($user)
            ->getJson("/api/projects/{$project->id}/activities")
            ->assertForbidden();
    }
});

test('guest tidak bisa melihat timeline project', function () {
    $project = createProject(User::factory()->create());

    $this->getJson("/api/projects/{$project->id}/activities")->assertUnauthorized();
});

test('timeline hanya berisi log project ini', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $other   = createProject($owner, ['name' => 'Lain']);

    $mine = createProjectActivity($project, $owner, ActivityAction::ProjectUpdated);
    createProjectActivity($other, $owner, ActivityAction::ProjectUpdated);

    $this->actingAs($owner)
        ->getJson("/api/projects/{$project->id}/activities")
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $mine->id);
});

test('timeline bisa difilter berdasarkan action, actor_id, dan task_id', function () {
    $owner  = User::factory()->create();
    $editor = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $editor, ProjectRole::Editor);
    $task = createTask($project);

    createProjectActivity($project, $owner, ActivityAction::ProjectUpdated);
    $byEditor = createProjectActivity($project, $editor, ActivityAction::InvitationSent);
    $onTask   = createTaskActivity($task, $owner);

    $this->actingAs($owner)
        ->getJson("/api/projects/{$project->id}/activities?action=invitation_sent")
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $byEditor->id);

    $this->actingAs($owner)
        ->getJson("/api/projects/{$project->id}/activities?actor_id={$editor->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $byEditor->id);

    $this->actingAs($owner)
        ->getJson("/api/projects/{$project->id}/activities?task_id={$task->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $onTask->id);
});

test('filter timeline yang tidak valid ditolak', function (string $query, string $field) {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $this->actingAs($owner)
        ->getJson("/api/projects/{$project->id}/activities?{$query}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'action tidak dikenal'   => ['action=unknown', 'action'],
    'actor_id bukan angka'   => ['actor_id=abc', 'actor_id'],
    'per_page nol'           => ['per_page=0', 'per_page'],
    'per_page terlalu besar' => ['per_page=101', 'per_page'],
]);

test('timeline mendukung pagination', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $first = createProjectActivity($project, $owner, ActivityAction::ProjectUpdated);
    createProjectActivity($project, $owner, ActivityAction::ProjectUpdated);
    createProjectActivity($project, $owner, ActivityAction::ProjectUpdated);

    $this->actingAs($owner)
        ->getJson("/api/projects/{$project->id}/activities?per_page=2&page=2")
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $first->id)
        ->assertJsonPath('data.meta.total', 3);
});

// ---------------------------------------------------------------- EVENT UNDANGAN

test('mengirim dan mencabut undangan tercatat di log project', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $invitationId = $this->actingAs($owner)
        ->postJson("/api/projects/{$project->id}/invitations", ['email' => 'baru@example.com', 'role' => 'editor'])
        ->assertCreated()
        ->json('data.id');

    $this->assertDatabaseHas('activity_logs', [
        'project_id'  => $project->id,
        'actor_id'    => $owner->id,
        'task_id'     => null,
        'action'      => 'invitation_sent',
        'description' => 'Invitation sent to baru@example.com as editor.',
    ]);

    $log = ActivityLog::query()->where('action', 'invitation_sent')->firstOrFail();

    expect($log->metadata)->toBe([
        'invitation_id' => $invitationId,
        'email'         => 'baru@example.com',
        'role'          => 'editor',
    ])->and($log->metadata)->not->toHaveKey('token');

    $this->actingAs($owner)
        ->deleteJson("/api/projects/{$project->id}/invitations/{$invitationId}")
        ->assertNoContent();

    $this->assertDatabaseHas('activity_logs', [
        'project_id'  => $project->id,
        'actor_id'    => $owner->id,
        'action'      => 'invitation_revoked',
        'description' => 'Invitation to baru@example.com revoked.',
    ]);
});

test('menerima dan menolak undangan tercatat dengan penerima sebagai aktor', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $accepter  = User::factory()->create(['email' => 'terima@example.com']);
    $decliner  = User::factory()->create(['email' => 'tolak@example.com']);
    $accepting = createInvitation($project, 'terima@example.com');
    $declining = createInvitation($project, 'tolak@example.com');

    $this->actingAs($accepter)
        ->postJson("/api/invitations/{$accepting->token}/accept")
        ->assertNoContent();

    $this->actingAs($decliner)
        ->postJson("/api/invitations/{$declining->token}/decline")
        ->assertNoContent();

    $this->assertDatabaseHas('activity_logs', [
        'project_id'  => $project->id,
        'actor_id'    => $accepter->id,
        'action'      => 'invitation_accepted',
        'description' => 'Invitation accepted by terima@example.com.',
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'project_id'  => $project->id,
        'actor_id'    => $decliner->id,
        'action'      => 'invitation_declined',
        'description' => 'Invitation declined by tolak@example.com.',
    ]);
});

test('undangan yang gagal diterima tidak meninggalkan log', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $user       = User::factory()->create(['email' => 'terlambat@example.com']);
    $invitation = createInvitation($project, 'terlambat@example.com', ['expires_at' => now()->subDay()]);

    $this->actingAs($user)
        ->postJson("/api/invitations/{$invitation->token}/accept")
        ->assertUnprocessable();

    $this->assertDatabaseMissing('activity_logs', ['action' => 'invitation_accepted']);
});

// ---------------------------------------------------------------- EVENT ANGGOTA

test('mengubah role member tercatat, mengubah ke role yang sama tidak', function () {
    $owner  = User::factory()->create();
    $member = User::factory()->create(['name' => 'Budi']);

    $project = createProject($owner);
    addMember($project, $member, ProjectRole::Viewer);

    $this->actingAs($owner)
        ->patchJson("/api/projects/{$project->id}/members/{$member->id}", ['role' => 'editor'])
        ->assertOk();

    $this->assertDatabaseHas('activity_logs', [
        'project_id'  => $project->id,
        'actor_id'    => $owner->id,
        'action'      => 'member_role_changed',
        'description' => "Budi's role changed from viewer to editor.",
    ]);

    $this->actingAs($owner)
        ->patchJson("/api/projects/{$project->id}/members/{$member->id}", ['role' => 'editor'])
        ->assertOk();

    expect(ActivityLog::query()->where('action', 'member_role_changed')->count())->toBe(1);
});

test('mengeluarkan member dan keluar sendiri tercatat dengan action berbeda', function () {
    $owner   = User::factory()->create();
    $removed = User::factory()->create(['name' => 'Dikeluarkan']);
    $leaver  = User::factory()->create(['name' => 'Keluar Sendiri']);

    $project = createProject($owner);
    addMember($project, $removed, ProjectRole::Editor);
    addMember($project, $leaver, ProjectRole::Viewer);

    $this->actingAs($owner)
        ->deleteJson("/api/projects/{$project->id}/members/{$removed->id}")
        ->assertNoContent();

    $this->actingAs($leaver)
        ->deleteJson("/api/projects/{$project->id}/members/{$leaver->id}")
        ->assertNoContent();

    $this->assertDatabaseHas('activity_logs', [
        'project_id'  => $project->id,
        'actor_id'    => $owner->id,
        'task_id'     => null,
        'action'      => 'member_removed',
        'description' => 'Dikeluarkan removed from the project.',
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'project_id'  => $project->id,
        'actor_id'    => $leaver->id,
        'action'      => 'member_left',
        'description' => 'Keluar Sendiri left the project.',
    ]);

    $log = ActivityLog::query()->where('action', 'member_removed')->firstOrFail();

    expect($log->metadata['user_id'])->toBe($removed->id)
        ->and($log->metadata['role'])->toBe('editor');
});

test('member yang tidak boleh dikeluarkan tidak meninggalkan log', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $this->actingAs($owner)
        ->deleteJson("/api/projects/{$project->id}/members/{$owner->id}")
        ->assertUnprocessable();

    $this->assertDatabaseMissing('activity_logs', ['action' => 'member_removed']);
    $this->assertDatabaseMissing('activity_logs', ['action' => 'member_left']);
});

// ---------------------------------------------------------------- EVENT PROJECT & TASK

test('mengubah project tercatat dan update tanpa perubahan tidak mencatat apa pun', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner, ['name' => 'Lama']);

    $this->actingAs($owner)
        ->patchJson("/api/projects/{$project->id}", ['name' => 'Baru'])
        ->assertOk();

    $log = ActivityLog::query()->where('action', 'project_updated')->firstOrFail();

    expect($log->actor_id)->toBe($owner->id)
        ->and($log->metadata['fields'])->toBe(['name'])
        ->and($log->metadata['project_name'])->toBe('Baru');

    $this->actingAs($owner)
        ->patchJson("/api/projects/{$project->id}", ['name' => 'Baru'])
        ->assertOk();

    expect(ActivityLog::query()->where('action', 'project_updated')->count())->toBe(1);
});

test('log penghapusan task tetap ada dan terbaca walau task sudah hilang', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project, ['title' => 'Task sementara']);

    $this->actingAs($owner)->deleteJson("/api/tasks/{$task->id}")->assertNoContent();

    $this->assertDatabaseMissing('tasks', ['id' => $task->id]);

    $this->actingAs($owner)
        ->getJson("/api/projects/{$project->id}/activities?action=task_deleted")
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.task_id', $task->id)
        ->assertJsonPath('data.items.0.metadata.task_title', 'Task sementara')
        ->assertJsonPath('data.items.0.description', 'Task "Task sementara" deleted.');
});

test('menghapus project ikut menghapus seluruh lognya', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    createProjectActivity($project, $owner, ActivityAction::ProjectUpdated);

    $this->actingAs($owner)->deleteJson("/api/projects/{$project->id}")->assertNoContent();

    $this->assertDatabaseMissing('activity_logs', ['project_id' => $project->id]);
});
