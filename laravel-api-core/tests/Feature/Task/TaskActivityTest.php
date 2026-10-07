<?php

use App\Enums\ProjectRole;
use App\Enums\ActivityAction;
use App\Models\User;

// ---------------------------------------------------------------- LIST

test('semua member termasuk viewer bisa melihat riwayat aktivitas, terbaru di atas', function () {
    $owner  = User::factory()->create(['name' => 'Pemilik']);
    $viewer = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $viewer, ProjectRole::Viewer);
    $task = createTask($project);

    $first  = createTaskActivity($task, $owner, [
        'action'      => ActivityAction::Created->value,
        'description' => 'Task created.',
    ]);
    $second = createTaskActivity($task, $owner, [
        'action'      => ActivityAction::StatusChanged->value,
        'description' => 'Status changed from todo to done.',
    ]);

    $this->actingAs($viewer)
        ->getJson("/api/tasks/{$task->id}/activities")
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items.0.id', $second->id)
        ->assertJsonPath('data.items.0.action', 'status_changed')
        ->assertJsonPath('data.items.0.user.id', $owner->id)
        ->assertJsonPath('data.items.0.user.name', 'Pemilik')
        ->assertJsonPath('data.items.1.id', $first->id)
        ->assertJsonPath('data.meta.total', 2);
});

test('aktivitas yang dihasilkan lewat API muncul di riwayat task', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $taskId = $this->actingAs($owner)
        ->postJson("/api/projects/{$project->id}/tasks", ['title' => 'Task baru'])
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($owner)
        ->patchJson("/api/tasks/{$taskId}", ['status' => 'done'])
        ->assertOk();

    $this->actingAs($owner)
        ->getJson("/api/tasks/{$taskId}/activities")
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items.0.action', 'status_changed')
        ->assertJsonPath('data.items.1.action', 'created');
});

test('riwayat hanya berisi aktivitas task tersebut', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $task      = createTask($project);
    $otherTask = createTask($project, ['title' => 'Task lain']);

    $mine = createTaskActivity($task, $owner);
    createTaskActivity($otherTask, $owner);

    $this->actingAs($owner)
        ->getJson("/api/tasks/{$task->id}/activities")
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $mine->id);
});

test('aktivitas tanpa user tetap tampil dengan user null', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project);

    createTaskActivity($task, null, ['description' => 'Dibuat oleh sistem.']);

    $this->actingAs($owner)
        ->getJson("/api/tasks/{$task->id}/activities")
        ->assertOk()
        ->assertJsonPath('data.items.0.description', 'Dibuat oleh sistem.')
        ->assertJsonPath('data.items.0.user', null);
});

test('riwayat aktivitas mendukung pagination', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project);

    $first = createTaskActivity($task, $owner);
    createTaskActivity($task, $owner);
    createTaskActivity($task, $owner);

    $this->actingAs($owner)
        ->getJson("/api/tasks/{$task->id}/activities?per_page=2")
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.meta.per_page', 2)
        ->assertJsonPath('data.meta.total', 3);

    $this->actingAs($owner)
        ->getJson("/api/tasks/{$task->id}/activities?per_page=2&page=2")
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $first->id);
});

test('per_page di luar batas ditolak', function (int $perPage) {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project);

    $this->actingAs($owner)
        ->getJson("/api/tasks/{$task->id}/activities?per_page={$perPage}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');
})->with([0, 101]);

// ---------------------------------------------------------------- AKSES

test('non-member tidak bisa melihat riwayat aktivitas task', function () {
    $project  = createProject(User::factory()->create());
    $task     = createTask($project);
    $outsider = User::factory()->create();

    createTaskActivity($task, null);

    $this->actingAs($outsider)
        ->getJson("/api/tasks/{$task->id}/activities")
        ->assertForbidden();
});

test('riwayat task yang tidak ada mengembalikan 404', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/tasks/999999/activities')
        ->assertNotFound();
});

test('guest tidak bisa melihat riwayat aktivitas', function () {
    $project = createProject(User::factory()->create());
    $task    = createTask($project);

    $this->getJson("/api/tasks/{$task->id}/activities")->assertUnauthorized();
});

test('riwayat mencatat pelepasan assignee saat member dikeluarkan dari project', function () {
    $owner  = User::factory()->create();
    $editor = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $editor, ProjectRole::Editor);
    $task = createTask($project, ['assigned_to' => $editor->id]);

    $this->actingAs($owner)
        ->deleteJson("/api/projects/{$project->id}/members/{$editor->id}")
        ->assertNoContent();

    $this->actingAs($owner)
        ->getJson("/api/tasks/{$task->id}/activities")
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.action', 'assigned')
        ->assertJsonPath('data.items.0.user.id', $owner->id)
        ->assertJsonPath(
            'data.items.0.description',
            'Task unassigned because the assignee was removed from the project.',
        );
});
