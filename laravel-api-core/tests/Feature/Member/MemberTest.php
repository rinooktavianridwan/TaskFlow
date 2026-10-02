<?php

use App\Enums\ProjectRole;
use App\Models\User;

// ---------------------------------------------------------------- LIST

test('semua member termasuk viewer bisa melihat daftar anggota', function () {
    $owner  = User::factory()->create();
    $viewer = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $viewer, ProjectRole::Viewer);

    $this->actingAs($viewer)
        ->getJson("/api/projects/{$project->id}/members")
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items.0.user_id', $owner->id)   // yang bergabung paling awal di atas
        ->assertJsonPath('data.items.0.role', 'owner')
        ->assertJsonPath('data.items.1.email', $viewer->email)
        ->assertJsonPath('data.meta.total', 2);
});

test('non-member tidak bisa melihat daftar anggota', function () {
    $project  = createProject(User::factory()->create());
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->getJson("/api/projects/{$project->id}/members")
        ->assertForbidden();
});

test('daftar anggota bisa difilter berdasarkan nama', function () {
    $owner = User::factory()->create(['name' => 'Pemilik']);
    $budi  = User::factory()->create(['name' => 'Budi Santoso']);
    $sari  = User::factory()->create(['name' => 'Sari Wulandari']);

    $project = createProject($owner);
    addMember($project, $budi, ProjectRole::Editor);
    addMember($project, $sari, ProjectRole::Viewer);

    $this->actingAs($owner)
        ->getJson("/api/projects/{$project->id}/members?name=budi")
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.user_id', $budi->id);
});

// ---------------------------------------------------------------- UPDATE ROLE

test('owner bisa mengubah role member', function () {
    $owner  = User::factory()->create();
    $member = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $member, ProjectRole::Viewer);

    $this->actingAs($owner)
        ->patchJson("/api/projects/{$project->id}/members/{$member->id}", ['role' => 'editor'])
        ->assertOk()
        ->assertJsonPath('data.user_id', $member->id)
        ->assertJsonPath('data.role', 'editor');

    $this->assertDatabaseHas('project_user', [
        'project_id' => $project->id,
        'user_id'    => $member->id,
        'role'       => 'editor',
    ]);
});

test('editor dan viewer tidak bisa mengubah role', function (string $role) {
    $owner  = User::factory()->create();
    $actor  = User::factory()->create();
    $target = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $actor, ProjectRole::from($role));
    addMember($project, $target, ProjectRole::Viewer);

    $this->actingAs($actor)
        ->patchJson("/api/projects/{$project->id}/members/{$target->id}", ['role' => 'owner'])
        ->assertForbidden();

    $this->assertDatabaseHas('project_user', ['user_id' => $target->id, 'role' => 'viewer']);
})->with(['editor', 'viewer']);

test('role yang tidak dikenal ditolak', function (array $payload) {
    $owner  = User::factory()->create();
    $member = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $member, ProjectRole::Viewer);

    $this->actingAs($owner)
        ->patchJson("/api/projects/{$project->id}/members/{$member->id}", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');
})->with([
    'role tidak dikenal' => [['role' => 'admin']],
    'role kosong'        => [[]],
]);

test('owner terakhir tidak bisa diturunkan rolenya', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $this->actingAs($owner)
        ->patchJson("/api/projects/{$project->id}/members/{$owner->id}", ['role' => 'editor'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');

    $this->assertDatabaseHas('project_user', ['user_id' => $owner->id, 'role' => 'owner']);
});

test('owner boleh diturunkan kalau masih ada owner lain', function () {
    $owner       = User::factory()->create();
    $secondOwner = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $secondOwner, ProjectRole::Owner);

    $this->actingAs($owner)
        ->patchJson("/api/projects/{$project->id}/members/{$owner->id}", ['role' => 'editor'])
        ->assertOk()
        ->assertJsonPath('data.role', 'editor');

    $this->assertDatabaseHas('project_user', ['user_id' => $owner->id, 'role' => 'editor']);
});

test('mengubah role user yang bukan member mengembalikan 404', function () {
    $owner    = User::factory()->create();
    $outsider = User::factory()->create();
    $project  = createProject($owner);

    $this->actingAs($owner)
        ->patchJson("/api/projects/{$project->id}/members/{$outsider->id}", ['role' => 'editor'])
        ->assertNotFound();
});

test('owner project lain tidak bisa mengubah role di project ini', function () {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();
    $member = User::factory()->create();

    createProject($ownerA);
    $projectB = createProject($ownerB);
    addMember($projectB, $member, ProjectRole::Viewer);

    $this->actingAs($ownerA)
        ->patchJson("/api/projects/{$projectB->id}/members/{$member->id}", ['role' => 'owner'])
        ->assertForbidden();

    $this->assertDatabaseHas('project_user', ['user_id' => $member->id, 'role' => 'viewer']);
});

// ---------------------------------------------------------------- REMOVE

test('owner bisa mengeluarkan member', function () {
    $owner  = User::factory()->create();
    $member = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $member, ProjectRole::Editor);

    $this->actingAs($owner)
        ->deleteJson("/api/projects/{$project->id}/members/{$member->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('project_user', [
        'project_id' => $project->id,
        'user_id'    => $member->id,
    ]);
});

test('member bisa keluar sendiri dari project', function () {
    $owner  = User::factory()->create();
    $member = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $member, ProjectRole::Viewer);

    $this->actingAs($member)
        ->deleteJson("/api/projects/{$project->id}/members/{$member->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('project_user', ['user_id' => $member->id]);
});

test('editor dan viewer tidak bisa mengeluarkan member lain', function (string $role) {
    $owner  = User::factory()->create();
    $actor  = User::factory()->create();
    $target = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $actor, ProjectRole::from($role));
    addMember($project, $target, ProjectRole::Viewer);

    $this->actingAs($actor)
        ->deleteJson("/api/projects/{$project->id}/members/{$target->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('project_user', ['user_id' => $target->id]);
})->with(['editor', 'viewer']);

test('owner terakhir tidak bisa dikeluarkan atau keluar sendiri', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $this->actingAs($owner)
        ->deleteJson("/api/projects/{$project->id}/members/{$owner->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');

    $this->assertDatabaseHas('project_user', ['user_id' => $owner->id, 'role' => 'owner']);
});

test('owner boleh keluar kalau masih ada owner lain', function () {
    $owner       = User::factory()->create();
    $secondOwner = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $secondOwner, ProjectRole::Owner);

    $this->actingAs($owner)
        ->deleteJson("/api/projects/{$project->id}/members/{$owner->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('project_user', ['user_id' => $owner->id]);
});

test('mengeluarkan user yang bukan member mengembalikan 404', function () {
    $owner    = User::factory()->create();
    $outsider = User::factory()->create();
    $project  = createProject($owner);

    $this->actingAs($owner)
        ->deleteJson("/api/projects/{$project->id}/members/{$outsider->id}")
        ->assertNotFound();
});
