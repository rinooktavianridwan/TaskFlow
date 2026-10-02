<?php

use App\Enums\ProjectRole;
use App\Models\User;

// ---------------------------------------------------------------- LIST

test('guest tidak bisa melihat daftar project', function () {
    $this->getJson('/api/projects')->assertUnauthorized();
});

test('daftar project hanya berisi project milik user yang login', function () {
    $user  = User::factory()->create();
    $other = User::factory()->create();

    $mine = createProject($user, ['name' => 'Punya Saya']);
    createProject($other, ['name' => 'Punya Orang Lain']);

    $this->actingAs($user)
        ->getJson('/api/projects')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $mine->id)
        ->assertJsonPath('data.meta.total', 1);
});

test('setiap project menampilkan role user yang sedang login', function () {
    $user = User::factory()->create();
    $boss = User::factory()->create();

    $owned  = createProject($user);
    $shared = createProject($boss);
    addMember($shared, $user, ProjectRole::Viewer);

    $response = $this->actingAs($user)->getJson('/api/projects')->assertOk();

    $roles = collect($response->json('data.items'))->pluck('role', 'id');

    expect($roles[$owned->id])->toBe('owner')
        ->and($roles[$shared->id])->toBe('viewer');
});

test('daftar project berurutan dari yang terbaru dan mendukung pagination', function () {
    $user = User::factory()->create();

    createProject($user, ['name' => 'Pertama']);
    $second = createProject($user, ['name' => 'Kedua']);
    $third  = createProject($user, ['name' => 'Ketiga']);

    $this->actingAs($user)
        ->getJson('/api/projects?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items.0.id', $third->id)
        ->assertJsonPath('data.items.1.id', $second->id)
        ->assertJsonPath('data.meta.per_page', 2)
        ->assertJsonPath('data.meta.total', 3);

    $this->actingAs($user)
        ->getJson('/api/projects?per_page=2&page=2')
        ->assertOk()
        ->assertJsonCount(1, 'data.items');
});

test('daftar project bisa difilter berdasarkan nama', function () {
    $user = User::factory()->create();

    $alpha = createProject($user, ['name' => 'Alpha Project']);
    createProject($user, ['name' => 'Beta Project']);

    $this->actingAs($user)
        ->getJson('/api/projects?name=alpha')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $alpha->id);
});

test('per_page di luar batas ditolak', function (int $perPage) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson("/api/projects?per_page={$perPage}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');
})->with([0, 101]);

// ---------------------------------------------------------------- STORE

test('user bisa membuat project dan otomatis menjadi owner', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/projects', ['name' => 'TaskFlow', 'description' => 'Project pertama'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'TaskFlow')
        ->assertJsonPath('data.role', 'owner');

    $projectId = $response->json('data.id');

    $this->assertDatabaseHas('projects', ['id' => $projectId, 'name' => 'TaskFlow']);
    $this->assertDatabaseHas('project_user', [
        'project_id' => $projectId,
        'user_id'    => $user->id,
        'role'       => 'owner',
    ]);
});

test('description boleh tidak diisi saat membuat project', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/projects', ['name' => 'Tanpa Deskripsi'])
        ->assertCreated()
        ->assertJsonPath('data.description', null);
});

test('nama project wajib diisi', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/projects', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    $this->assertDatabaseCount('projects', 0);
});

test('guest tidak bisa membuat project', function () {
    $this->postJson('/api/projects', ['name' => 'X'])->assertUnauthorized();

    $this->assertDatabaseCount('projects', 0);
});

// ---------------------------------------------------------------- SHOW

test('member dengan role apa pun bisa melihat project beserta role-nya', function (string $role) {
    $boss   = User::factory()->create();
    $member = User::factory()->create();

    $project = createProject($boss);
    addMember($project, $member, ProjectRole::from($role));

    $this->actingAs($member)
        ->getJson("/api/projects/{$project->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $project->id)
        ->assertJsonPath('data.role', $role);
})->with(['owner', 'editor', 'viewer']);

test('non-member tidak bisa melihat project', function () {
    $project  = createProject(User::factory()->create());
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->getJson("/api/projects/{$project->id}")
        ->assertForbidden();
});

test('project yang tidak ada mengembalikan 404', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/projects/999999')->assertNotFound();
});

// ---------------------------------------------------------------- UPDATE

test('owner bisa mengubah project', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner, ['name' => 'Lama']);

    $this->actingAs($owner)
        ->patchJson("/api/projects/{$project->id}", ['name' => 'Baru'])
        ->assertOk()
        ->assertJsonPath('data.id', $project->id)
        ->assertJsonPath('data.name', 'Baru')
        ->assertJsonPath('data.role', 'owner');

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Baru']);
});

test('update sebagian tidak mengubah field lain', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner, ['name' => 'Awal', 'description' => 'Deskripsi lama']);

    $this->actingAs($owner)
        ->patchJson("/api/projects/{$project->id}", ['description' => 'Deskripsi baru'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Awal')
        ->assertJsonPath('data.description', 'Deskripsi baru');

    $this->assertDatabaseHas('projects', [
        'id'          => $project->id,
        'name'        => 'Awal',
        'description' => 'Deskripsi baru',
    ]);
});

test('nama tidak boleh dikosongkan saat update', function (?string $name) {
    $owner   = User::factory()->create();
    $project = createProject($owner, ['name' => 'Tetap']);

    $this->actingAs($owner)
        ->patchJson("/api/projects/{$project->id}", ['name' => $name])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Tetap']);
})->with([null, '']);

test('editor dan viewer tidak bisa mengubah project', function (string $role) {
    $owner  = User::factory()->create();
    $member = User::factory()->create();

    $project = createProject($owner, ['name' => 'Asli']);
    addMember($project, $member, ProjectRole::from($role));

    $this->actingAs($member)
        ->patchJson("/api/projects/{$project->id}", ['name' => 'Diubah'])
        ->assertForbidden();

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Asli']);
})->with(['editor', 'viewer']);

test('non-member tidak bisa mengubah project', function () {
    $project  = createProject(User::factory()->create(), ['name' => 'Asli']);
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->patchJson("/api/projects/{$project->id}", ['name' => 'Diubah'])
        ->assertForbidden();
});

// ---------------------------------------------------------------- DESTROY

test('owner bisa menghapus project', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $this->actingAs($owner)
        ->deleteJson("/api/projects/{$project->id}")
        ->assertNoContent();

    $this->assertModelMissing($project);
});

test('editor dan viewer tidak bisa menghapus project', function (string $role) {
    $owner  = User::factory()->create();
    $member = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $member, ProjectRole::from($role));

    $this->actingAs($member)
        ->deleteJson("/api/projects/{$project->id}")
        ->assertForbidden();

    $this->assertModelExists($project);
})->with(['editor', 'viewer']);

test('non-member tidak bisa menghapus project', function () {
    $project  = createProject(User::factory()->create());
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->deleteJson("/api/projects/{$project->id}")
        ->assertForbidden();

    $this->assertModelExists($project);
});

test('menghapus project ikut menghapus anggota dan undangannya', function () {
    $owner  = User::factory()->create();
    $member = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $member, ProjectRole::Viewer);
    $invitation = createInvitation($project, 'x@example.com');

    $this->actingAs($owner)
        ->deleteJson("/api/projects/{$project->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('project_user', ['project_id' => $project->id]);
    $this->assertModelMissing($invitation);
});
