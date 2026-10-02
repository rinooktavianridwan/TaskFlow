<?php

use App\Enums\ProjectRole;
use App\Enums\TaskActivityAction;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;

// ---------------------------------------------------------------- LIST

test('member bisa melihat daftar task project dan memfilter hasilnya', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $firstTask = createTask($project, [
        'title'  => 'Rancang API',
        'status' => TaskStatus::InProgress->value,
    ]);

    createTask($project, [
        'title'  => 'Perbaiki tampilan',
        'status' => TaskStatus::Todo->value,
    ]);

    $otherProject = createProject(User::factory()->create());
    createTask($otherProject, ['title' => 'Task project lain']);

    $this->actingAs($owner)
        ->getJson("/api/projects/{$project->id}/tasks?title=API&status=in_progress")
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $firstTask->id)
        ->assertJsonPath('data.items.0.title', 'Rancang API')
        ->assertJsonPath('data.meta.total', 1);
});

test('non-member tidak bisa melihat daftar task project', function () {
    $project  = createProject(User::factory()->create());
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->getJson("/api/projects/{$project->id}/tasks")
        ->assertForbidden();
});

test('filter daftar task yang tidak valid ditolak', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $this->actingAs($owner)
        ->getJson("/api/projects/{$project->id}/tasks?status=unknown")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

test('daftar task berurutan dari yang terbaru dan mendukung pagination', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $first  = createTask($project, ['title' => 'Pertama']);
    $second = createTask($project, ['title' => 'Kedua']);
    $third  = createTask($project, ['title' => 'Ketiga']);

    $this->actingAs($owner)
        ->getJson("/api/projects/{$project->id}/tasks?per_page=2")
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items.0.id', $third->id)
        ->assertJsonPath('data.items.1.id', $second->id)
        ->assertJsonPath('data.meta.per_page', 2)
        ->assertJsonPath('data.meta.total', 3);

    $this->actingAs($owner)
        ->getJson("/api/projects/{$project->id}/tasks?per_page=2&page=2")
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $first->id);
});

test('per_page di luar batas ditolak', function (int $perPage) {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $this->actingAs($owner)
        ->getJson("/api/projects/{$project->id}/tasks?per_page={$perPage}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');
})->with([0, 101]);

// ---------------------------------------------------------------- CREATE

test('owner bisa membuat task tanpa assignee dan status awal todo', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $this->actingAs($owner)
        ->postJson("/api/projects/{$project->id}/tasks", [
            'title'       => 'Buat endpoint',
            'description' => 'Endpoint untuk task',
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Buat endpoint')
        ->assertJsonPath('data.status', TaskStatus::Todo->value)
        ->assertJsonPath('data.assigned_to', null);

    $this->assertDatabaseHas('tasks', [
        'project_id'  => $project->id,
        'title'       => 'Buat endpoint',
        'status'      => TaskStatus::Todo->value,
        'assigned_to' => null,
    ]);

    $task = Task::query()->where('project_id', $project->id)->firstOrFail();

    $this->assertDatabaseHas('task_activities', [
        'task_id' => $task->id,
        'user_id' => $owner->id,
        'action'  => TaskActivityAction::Created->value,
    ]);
});

test('editor bisa membuat task dan menugaskannya kepada member project', function () {
    $owner    = User::factory()->create();
    $editor   = User::factory()->create();
    $assignee = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $editor, ProjectRole::Editor);
    addMember($project, $assignee, ProjectRole::Viewer);

    $this->actingAs($editor)
        ->postJson("/api/projects/{$project->id}/tasks", [
            'title'       => 'Implementasi fitur',
            'assigned_to' => $assignee->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.assigned_to', $assignee->id)
        ->assertJsonPath('data.assignee.id', $assignee->id);
});

test('viewer tidak bisa membuat task', function () {
    $owner  = User::factory()->create();
    $viewer = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $viewer, ProjectRole::Viewer);

    $this->actingAs($viewer)
        ->postJson("/api/projects/{$project->id}/tasks", [
            'title' => 'Task yang ditolak',
        ])
        ->assertForbidden();
});

test('assignee yang bukan member project ditolak', function () {
    $owner    = User::factory()->create();
    $outsider = User::factory()->create();
    $project  = createProject($owner);

    $this->actingAs($owner)
        ->postJson("/api/projects/{$project->id}/tasks", [
            'title'       => 'Task dengan assignee tidak valid',
            'assigned_to' => $outsider->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('assigned_to');
});

test('payload pembuatan task yang tidak valid ditolak', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $this->actingAs($owner)
        ->postJson("/api/projects/{$project->id}/tasks", [
            'title'    => '',
            'due_date' => 'bukan-tanggal',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'due_date']);
});

test('status tidak bisa ditentukan saat membuat task', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $this->actingAs($owner)
        ->postJson("/api/projects/{$project->id}/tasks", [
            'title'  => 'Task langsung selesai?',
            'status' => TaskStatus::Done->value,
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', TaskStatus::Todo->value);
});

// ---------------------------------------------------------------- SHOW

test('member bisa melihat detail task beserta assignee', function () {
    $owner    = User::factory()->create();
    $assignee = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $assignee, ProjectRole::Viewer);

    $task = createTask($project, [
        'title'       => 'Task detail',
        'assigned_to' => $assignee->id,
    ]);

    $this->actingAs($assignee)
        ->getJson("/api/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $task->id)
        ->assertJsonPath('data.assignee.id', $assignee->id);
});

test('non-member tidak bisa melihat detail task', function () {
    $project  = createProject(User::factory()->create());
    $task     = createTask($project);
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->getJson("/api/tasks/{$task->id}")
        ->assertForbidden();
});

// ---------------------------------------------------------------- UPDATE

test('owner bisa mengubah detail task dan menerima response task terbaru', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project, ['title' => 'Judul lama']);

    $this->actingAs($owner)
        ->patchJson("/api/tasks/{$task->id}", [
            'title'       => 'Judul baru',
            'description' => 'Deskripsi baru',
            'status'      => TaskStatus::InProgress->value,
        ])
        ->assertOk()
        ->assertJsonPath('data.id', $task->id)
        ->assertJsonPath('data.title', 'Judul baru')
        ->assertJsonPath('data.description', 'Deskripsi baru')
        ->assertJsonPath('data.status', TaskStatus::InProgress->value);

    $this->assertDatabaseHas('task_activities', [
        'task_id' => $task->id,
        'user_id' => $owner->id,
        'action'  => TaskActivityAction::StatusChanged->value,
    ]);

    $this->assertDatabaseHas('task_activities', [
        'task_id' => $task->id,
        'user_id' => $owner->id,
        'action'  => TaskActivityAction::Updated->value,
    ]);
});

test('editor bisa mengubah assignee ke member project lain', function () {
    $owner       = User::factory()->create();
    $editor      = User::factory()->create();
    $newAssignee = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $editor, ProjectRole::Editor);
    addMember($project, $newAssignee, ProjectRole::Viewer);

    $task = createTask($project);

    $this->actingAs($editor)
        ->patchJson("/api/tasks/{$task->id}", [
            'assigned_to' => $newAssignee->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.assigned_to', $newAssignee->id);

    $this->assertDatabaseHas('task_activities', [
        'task_id' => $task->id,
        'user_id' => $editor->id,
        'action'  => TaskActivityAction::Assigned->value,
    ]);
});

test('assignee hanya bisa mengubah status task yang ditugaskan kepadanya', function () {
    $owner    = User::factory()->create();
    $assignee = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $assignee, ProjectRole::Viewer);

    $task = createTask($project, [
        'assigned_to' => $assignee->id,
    ]);

    $this->actingAs($assignee)
        ->patchJson("/api/tasks/{$task->id}", [
            'status' => TaskStatus::Done->value,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', TaskStatus::Done->value);

    $this->assertDatabaseHas('task_activities', [
        'task_id' => $task->id,
        'user_id' => $assignee->id,
        'action'  => TaskActivityAction::StatusChanged->value,
    ]);
});

test('assignee tidak bisa mengubah field lain selain status', function () {
    $owner    = User::factory()->create();
    $assignee = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $assignee, ProjectRole::Viewer);

    $task = createTask($project, [
        'assigned_to' => $assignee->id,
    ]);

    $this->actingAs($assignee)
        ->patchJson("/api/tasks/{$task->id}", [
            'title'  => 'Judul diubah',
            'status' => TaskStatus::Done->value,
        ])
        ->assertForbidden();

    $this->assertDatabaseHas('tasks', [
        'id'     => $task->id,
        'title'  => 'Task test',
        'status' => TaskStatus::Todo->value,
    ]);
});

test('member yang bukan assignee tidak bisa mengubah status task', function () {
    $owner  = User::factory()->create();
    $member = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $member, ProjectRole::Viewer);

    $task = createTask($project);

    $this->actingAs($member)
        ->patchJson("/api/tasks/{$task->id}", [
            'status' => TaskStatus::Done->value,
        ])
        ->assertForbidden();
});

test('assignee dari luar project dan status yang tidak dikenal ditolak', function () {
    $owner    = User::factory()->create();
    $outsider = User::factory()->create();
    $project  = createProject($owner);
    $task     = createTask($project);

    $this->actingAs($owner)
        ->patchJson("/api/tasks/{$task->id}", [
            'assigned_to' => $outsider->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('assigned_to');

    $this->actingAs($owner)
        ->patchJson("/api/tasks/{$task->id}", [
            'status' => 'unknown',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

test('payload update task yang tidak valid ditolak', function (array $payload, string $field) {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project);

    $this->actingAs($owner)
        ->patchJson("/api/tasks/{$task->id}", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'title kosong'           => [['title' => ''], 'title'],
    'title null'             => [['title' => null], 'title'],
    'status tidak dikenal'   => [['status' => 'unknown'], 'status'],
    'due_date bukan tanggal' => [['due_date' => 'bukan-tanggal'], 'due_date'],
]);

test('owner bisa mencabut assignee dari task', function () {
    $owner    = User::factory()->create();
    $assignee = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $assignee, ProjectRole::Viewer);
    $task = createTask($project, ['assigned_to' => $assignee->id]);

    $this->actingAs($owner)
        ->patchJson("/api/tasks/{$task->id}", ['assigned_to' => null])
        ->assertOk()
        ->assertJsonPath('data.assigned_to', null);

    $this->assertDatabaseHas('task_activities', [
        'task_id'     => $task->id,
        'action'      => TaskActivityAction::Assigned->value,
        'description' => 'Task unassigned.',
    ]);
});

test('update tanpa perubahan tidak mencatat aktivitas', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project, [
        'title'  => 'Judul sama',
        'status' => TaskStatus::InProgress->value,
    ]);

    $this->actingAs($owner)
        ->patchJson("/api/tasks/{$task->id}", [
            'title'  => 'Judul sama',
            'status' => TaskStatus::InProgress->value,
        ])
        ->assertOk();

    $this->assertDatabaseCount('task_activities', 0);
});

// ---------------------------------------------------------------- DELETE

test('owner bisa menghapus task', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project);

    $this->actingAs($owner)
        ->deleteJson("/api/tasks/{$task->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
});

test('viewer tidak bisa menghapus task', function () {
    $owner  = User::factory()->create();
    $viewer = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $viewer, ProjectRole::Viewer);
    $task = createTask($project);

    $this->actingAs($viewer)
        ->deleteJson("/api/tasks/{$task->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('tasks', ['id' => $task->id]);
});

// ---------------------------------------------------------------- AKSES

test('non-member tidak bisa membuat, mengubah, atau menghapus task', function () {
    $project  = createProject(User::factory()->create());
    $task     = createTask($project, ['title' => 'Task asli']);
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->postJson("/api/projects/{$project->id}/tasks", ['title' => 'Task baru'])
        ->assertForbidden();

    $this->actingAs($outsider)
        ->patchJson("/api/tasks/{$task->id}", ['title' => 'Diubah'])
        ->assertForbidden();

    $this->actingAs($outsider)
        ->deleteJson("/api/tasks/{$task->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Task asli']);
    $this->assertDatabaseMissing('tasks', ['title' => 'Task baru']);
});

test('non-member mendapat 403 meski payload update tidak valid', function () {
    $project  = createProject(User::factory()->create());
    $task     = createTask($project);
    $outsider = User::factory()->create();

    // Membuktikan can:view,task berjalan SEBELUM validasi (403, bukan 422).
    $this->actingAs($outsider)
        ->patchJson("/api/tasks/{$task->id}", ['title' => ''])
        ->assertForbidden();
});

test('editor bisa menghapus task', function () {
    $owner  = User::factory()->create();
    $editor = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $editor, ProjectRole::Editor);
    $task = createTask($project);

    $this->actingAs($editor)
        ->deleteJson("/api/tasks/{$task->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
});

test('task yang tidak ada mengembalikan 404', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/tasks/999999')->assertNotFound();
    $this->actingAs($user)->patchJson('/api/tasks/999999', ['title' => 'x'])->assertNotFound();
    $this->actingAs($user)->deleteJson('/api/tasks/999999')->assertNotFound();
});

// ---------------------------------------------------------------- AUTHENTICATION

test('request task tanpa login ditolak', function () {
    $project = createProject(User::factory()->create());
    $task    = createTask($project);

    $this->getJson("/api/projects/{$project->id}/tasks")->assertUnauthorized();
    $this->postJson("/api/projects/{$project->id}/tasks", ['title' => 'Test'])->assertUnauthorized();
    $this->getJson("/api/tasks/{$task->id}")->assertUnauthorized();
    $this->patchJson("/api/tasks/{$task->id}", ['status' => TaskStatus::Done->value])->assertUnauthorized();
    $this->deleteJson("/api/tasks/{$task->id}")->assertUnauthorized();
});
