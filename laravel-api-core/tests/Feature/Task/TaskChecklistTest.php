<?php

use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use App\Jobs\SyncTaskReminderJob;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

// ---------------------------------------------------------------- CREATE

test('owner dan editor bisa menambah item checklist dengan posisi berurutan', function () {
    $owner  = User::factory()->create();
    $editor = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $editor, ProjectRole::Editor);
    $task = createTask($project);

    $this->actingAs($owner)
        ->postJson("/api/tasks/{$task->id}/checklist-items", ['title' => 'Tulis test'])
        ->assertCreated()
        ->assertJsonPath('data.item.title', 'Tulis test')
        ->assertJsonPath('data.item.is_done', false)
        ->assertJsonPath('data.item.position', 1)
        ->assertJsonPath('data.task.checklist_total', 1)
        ->assertJsonPath('data.task.progress', 0);

    $this->actingAs($editor)
        ->postJson("/api/tasks/{$task->id}/checklist-items", ['title' => 'Review'])
        ->assertCreated()
        ->assertJsonPath('data.item.position', 2)
        ->assertJsonPath('data.task.checklist_total', 2);
});

test('viewer dan non-member tidak bisa menambah item checklist', function () {
    $owner    = User::factory()->create();
    $viewer   = User::factory()->create();
    $outsider = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $viewer, ProjectRole::Viewer);
    $task = createTask($project, ['assigned_to' => $viewer->id]);

    $this->actingAs($viewer)
        ->postJson("/api/tasks/{$task->id}/checklist-items", ['title' => 'Ditolak'])
        ->assertForbidden();

    $this->actingAs($outsider)
        ->postJson("/api/tasks/{$task->id}/checklist-items", ['title' => 'Ditolak'])
        ->assertForbidden();

    $this->assertDatabaseCount('task_checklist_items', 0);
});

test('judul item checklist wajib diisi', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project);

    $this->actingAs($owner)
        ->postJson("/api/tasks/{$task->id}/checklist-items", ['title' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('title');
});

test('satu task dibatasi maksimal 50 item checklist', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project);

    for ($i = 1; $i <= 50; $i++) {
        createChecklistItem($task, ['title' => "Item {$i}", 'position' => $i]);
    }

    $this->actingAs($owner)
        ->postJson("/api/tasks/{$task->id}/checklist-items", ['title' => 'Item ke-51'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('title');

    $this->assertDatabaseCount('task_checklist_items', 50);
});

test('guest tidak bisa mengakses endpoint checklist', function () {
    $project = createProject(User::factory()->create());
    $task    = createTask($project);
    $item    = createChecklistItem($task);

    $this->postJson("/api/tasks/{$task->id}/checklist-items", ['title' => 'X'])->assertUnauthorized();
    $this->patchJson("/api/checklist-items/{$item->id}", ['is_done' => true])->assertUnauthorized();
    $this->deleteJson("/api/checklist-items/{$item->id}")->assertUnauthorized();
});

// ---------------------------------------------------------------- UPDATE & IZIN

test('editor bisa mengubah judul item tanpa mengubah status task', function () {
    $owner  = User::factory()->create();
    $editor = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $editor, ProjectRole::Editor);
    $task = createTask($project);
    $item = createChecklistItem($task, ['title' => 'Lama']);

    $this->actingAs($editor)
        ->patchJson("/api/checklist-items/{$item->id}", ['title' => 'Baru'])
        ->assertOk()
        ->assertJsonPath('data.item.title', 'Baru')
        ->assertJsonPath('data.task.status', TaskStatus::Todo->value);

    $this->assertDatabaseCount('activity_logs', 0);
});

test('viewer yang ditugaskan hanya boleh mencentang item, bukan mengubah judul', function () {
    $owner  = User::factory()->create();
    $viewer = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $viewer, ProjectRole::Viewer);
    $task = createTask($project, ['assigned_to' => $viewer->id]);
    $item = createChecklistItem($task, ['title' => 'Asli']);
    createChecklistItem($task, ['position' => 2]);

    $this->actingAs($viewer)
        ->patchJson("/api/checklist-items/{$item->id}", ['title' => 'Diubah', 'is_done' => true])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->patchJson("/api/checklist-items/{$item->id}", ['title' => 'Diubah'])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->patchJson("/api/checklist-items/{$item->id}", ['is_done' => true])
        ->assertOk()
        ->assertJsonPath('data.item.is_done', true);

    $this->assertDatabaseHas('task_checklist_items', ['id' => $item->id, 'title' => 'Asli', 'is_done' => true]);
});

test('member yang bukan assignee dan non-member tidak bisa mencentang item', function () {
    $owner    = User::factory()->create();
    $viewer   = User::factory()->create();
    $outsider = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $viewer, ProjectRole::Viewer);
    $task = createTask($project);
    $item = createChecklistItem($task);

    $this->actingAs($viewer)
        ->patchJson("/api/checklist-items/{$item->id}", ['is_done' => true])
        ->assertForbidden();

    $this->actingAs($outsider)
        ->patchJson("/api/checklist-items/{$item->id}", ['is_done' => true])
        ->assertForbidden();

    $this->assertDatabaseHas('task_checklist_items', ['id' => $item->id, 'is_done' => false]);
});

test('payload update item yang tidak valid ditolak', function (array $payload, string $field) {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project);
    $item    = createChecklistItem($task);

    $this->actingAs($owner)
        ->patchJson("/api/checklist-items/{$item->id}", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'body kosong'           => [[], 'title'],
    'title kosong'          => [['title' => ''], 'title'],
    'is_done bukan boolean' => [['is_done' => 'abc'], 'is_done'],
]);

test('item yang tidak ada mengembalikan 404', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patchJson('/api/checklist-items/999999', ['is_done' => true])->assertNotFound();
    $this->actingAs($user)->deleteJson('/api/checklist-items/999999')->assertNotFound();
});

// ---------------------------------------------------------------- DELETE

test('owner bisa menghapus item, viewer tidak bisa', function () {
    $owner  = User::factory()->create();
    $viewer = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $viewer, ProjectRole::Viewer);
    $task = createTask($project, ['assigned_to' => $viewer->id]);
    $item = createChecklistItem($task);

    $this->actingAs($viewer)
        ->deleteJson("/api/checklist-items/{$item->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('task_checklist_items', ['id' => $item->id]);

    $this->actingAs($owner)
        ->deleteJson("/api/checklist-items/{$item->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('task_checklist_items', ['id' => $item->id]);
});

test('menghapus task ikut menghapus item checklist-nya', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project);
    createChecklistItem($task);

    $this->actingAs($owner)->deleteJson("/api/tasks/{$task->id}")->assertNoContent();

    $this->assertDatabaseCount('task_checklist_items', 0);
});

// ---------------------------------------------------------------- STATUS OTOMATIS

test('mencentang item pertama mengubah task todo menjadi in_progress', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project);
    $first   = createChecklistItem($task, ['title' => 'A', 'position' => 1]);
    createChecklistItem($task, ['title' => 'B', 'position' => 2]);

    $this->actingAs($owner)
        ->patchJson("/api/checklist-items/{$first->id}", ['is_done' => true])
        ->assertOk()
        ->assertJsonPath('data.item.is_done', true)
        ->assertJsonPath('data.task.status', TaskStatus::InProgress->value)
        ->assertJsonPath('data.task.checklist_total', 2)
        ->assertJsonPath('data.task.checklist_done', 1)
        ->assertJsonPath('data.task.progress', 50);

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => TaskStatus::InProgress->value]);
    $this->assertDatabaseHas('activity_logs', [
        'task_id'     => $task->id,
        'actor_id'    => $owner->id,
        'action'      => 'status_changed',
        'description' => 'Status changed from todo to in_progress (via checklist).',
    ]);
});

test('mencentang semua item mengubah task menjadi done', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project, ['status' => TaskStatus::InProgress->value]);
    createChecklistItem($task, ['is_done' => true, 'position' => 1]);
    $last = createChecklistItem($task, ['position' => 2]);

    $this->actingAs($owner)
        ->patchJson("/api/checklist-items/{$last->id}", ['is_done' => true])
        ->assertOk()
        ->assertJsonPath('data.task.status', TaskStatus::Done->value)
        ->assertJsonPath('data.task.progress', 100);

    $this->assertDatabaseHas('activity_logs', [
        'task_id'     => $task->id,
        'description' => 'Status changed from in_progress to done (via checklist).',
    ]);
});

test('mencentang item pada task in_progress yang belum tuntas tidak mengubah status', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project, ['status' => TaskStatus::InProgress->value]);
    $item    = createChecklistItem($task, ['position' => 1]);
    createChecklistItem($task, ['position' => 2]);
    createChecklistItem($task, ['position' => 3]);

    $this->actingAs($owner)
        ->patchJson("/api/checklist-items/{$item->id}", ['is_done' => true])
        ->assertOk()
        ->assertJsonPath('data.task.status', TaskStatus::InProgress->value)
        ->assertJsonPath('data.task.progress', 33);

    $this->assertDatabaseMissing('activity_logs', ['task_id' => $task->id, 'action' => 'status_changed']);
    $this->assertDatabaseHas('activity_logs', ['task_id' => $task->id, 'action' => 'checklist_item_completed']);
});

test('menghapus centang saat task done mengembalikannya ke in_progress', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project, ['status' => TaskStatus::Done->value]);
    $first   = createChecklistItem($task, ['is_done' => true, 'position' => 1]);
    createChecklistItem($task, ['is_done' => true, 'position' => 2]);

    $this->actingAs($owner)
        ->patchJson("/api/checklist-items/{$first->id}", ['is_done' => false])
        ->assertOk()
        ->assertJsonPath('data.task.status', TaskStatus::InProgress->value)
        ->assertJsonPath('data.task.progress', 50);
});

test('menambah item baru pada task done membukanya kembali menjadi in_progress', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project, ['status' => TaskStatus::Done->value]);
    createChecklistItem($task, ['is_done' => true]);

    $this->actingAs($owner)
        ->postJson("/api/tasks/{$task->id}/checklist-items", ['title' => 'Tambahan'])
        ->assertCreated()
        ->assertJsonPath('data.task.status', TaskStatus::InProgress->value)
        ->assertJsonPath('data.task.progress', 50);
});

test('menambah item pada task todo tidak mengubah status', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project);

    $this->actingAs($owner)
        ->postJson("/api/tasks/{$task->id}/checklist-items", ['title' => 'Pertama'])
        ->assertCreated()
        ->assertJsonPath('data.task.status', TaskStatus::Todo->value);
});

test('menghapus item terakhir yang belum selesai menyelesaikan task', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project, ['status' => TaskStatus::InProgress->value]);
    createChecklistItem($task, ['is_done' => true, 'position' => 1]);
    $pending = createChecklistItem($task, ['position' => 2]);

    $this->actingAs($owner)
        ->deleteJson("/api/checklist-items/{$pending->id}")
        ->assertNoContent();

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => TaskStatus::Done->value]);
});

test('menghapus semua item tidak mengubah status task', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project, ['status' => TaskStatus::InProgress->value]);
    $item    = createChecklistItem($task);

    $this->actingAs($owner)
        ->deleteJson("/api/checklist-items/{$item->id}")
        ->assertNoContent();

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => TaskStatus::InProgress->value]);
    $this->assertDatabaseMissing('activity_logs', ['task_id' => $task->id, 'action' => 'status_changed']);
    $this->assertDatabaseHas('activity_logs', ['task_id' => $task->id, 'action' => 'checklist_item_removed']);
});

test('viewer yang ditugaskan bisa menyelesaikan task lewat centang terakhir', function () {
    $owner  = User::factory()->create();
    $viewer = User::factory()->create();

    $project = createProject($owner);
    addMember($project, $viewer, ProjectRole::Viewer);
    $task = createTask($project, ['assigned_to' => $viewer->id]);
    $item = createChecklistItem($task);

    $this->actingAs($viewer)
        ->patchJson("/api/checklist-items/{$item->id}", ['is_done' => true])
        ->assertOk()
        ->assertJsonPath('data.task.status', TaskStatus::Done->value);

    $this->assertDatabaseHas('activity_logs', [
        'task_id'  => $task->id,
        'actor_id' => $viewer->id,
        'action'   => 'status_changed',
    ]);
});

// ---------------------------------------------------------------- STATUS MANUAL & PROGRESS

test('status done manual membuat progress langsung 100 tanpa menyentuh item', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project);
    createChecklistItem($task, ['position' => 1]);
    createChecklistItem($task, ['position' => 2]);
    createChecklistItem($task, ['position' => 3]);

    $this->actingAs($owner)
        ->patchJson("/api/tasks/{$task->id}", ['status' => TaskStatus::Done->value])
        ->assertOk()
        ->assertJsonPath('data.progress', 100)
        ->assertJsonPath('data.checklist_total', 3)
        ->assertJsonPath('data.checklist_done', 0);

    $this->assertDatabaseMissing('task_checklist_items', ['task_id' => $task->id, 'is_done' => true]);
});

test('mencentang item pada task done manual tidak membukanya kembali', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project, ['status' => TaskStatus::Done->value]);
    $item    = createChecklistItem($task, ['position' => 1]);
    createChecklistItem($task, ['position' => 2]);

    $this->actingAs($owner)
        ->patchJson("/api/checklist-items/{$item->id}", ['is_done' => true])
        ->assertOk()
        ->assertJsonPath('data.task.status', TaskStatus::Done->value)
        ->assertJsonPath('data.task.progress', 100);
});

test('progress kembali mengikuti checklist saat status done diubah manual', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project, ['status' => TaskStatus::Done->value]);
    createChecklistItem($task, ['is_done' => true, 'position' => 1]);
    createChecklistItem($task, ['position' => 2]);

    $this->actingAs($owner)
        ->patchJson("/api/tasks/{$task->id}", ['status' => TaskStatus::Todo->value])
        ->assertOk()
        ->assertJsonPath('data.progress', 50);
});

test('task tanpa checklist: progress 0 sampai status done', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project);

    $this->actingAs($owner)
        ->getJson("/api/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('data.checklist_total', 0)
        ->assertJsonPath('data.progress', 0);

    $this->actingAs($owner)
        ->patchJson("/api/tasks/{$task->id}", ['status' => TaskStatus::InProgress->value])
        ->assertOk()
        ->assertJsonPath('data.progress', 0);

    $this->actingAs($owner)
        ->patchJson("/api/tasks/{$task->id}", ['status' => TaskStatus::Done->value])
        ->assertOk()
        ->assertJsonPath('data.progress', 100);
});

// ---------------------------------------------------------------- TAMPILAN

test('detail task menampilkan checklist berurutan menurut posisi', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project);

    createChecklistItem($task, ['title' => 'Kedua', 'position' => 2]);
    createChecklistItem($task, ['title' => 'Pertama', 'position' => 1]);

    $this->actingAs($owner)
        ->getJson("/api/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.checklist')
        ->assertJsonPath('data.checklist.0.title', 'Pertama')
        ->assertJsonPath('data.checklist.1.title', 'Kedua');
});

test('daftar task menampilkan ringkasan checklist dan progress tiap task', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $withChecklist = createTask($project, ['title' => 'Dengan checklist']);
    createChecklistItem($withChecklist, ['is_done' => true, 'position' => 1]);
    createChecklistItem($withChecklist, ['position' => 2]);

    $plain = createTask($project, ['title' => 'Tanpa checklist']);

    $response = $this->actingAs($owner)
        ->getJson("/api/projects/{$project->id}/tasks")
        ->assertOk();

    $items = collect($response->json('data.items'))->keyBy('id');

    expect($items[$withChecklist->id]['checklist_total'])->toBe(2)
        ->and($items[$withChecklist->id]['checklist_done'])->toBe(1)
        ->and($items[$withChecklist->id]['progress'])->toBe(50)
        ->and($items[$plain->id]['checklist_total'])->toBe(0)
        ->and($items[$plain->id]['progress'])->toBe(0);
});

// ---------------------------------------------------------------- REMINDER

test('perubahan status otomatis mendispatch sinkronisasi reminder', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project, [
        'assigned_to' => $owner->id,
        'due_date'    => '2030-01-01 10:00:00',
    ]);
    $item    = createChecklistItem($task);

    $this->actingAs($owner)
        ->patchJson("/api/checklist-items/{$item->id}", ['is_done' => true])
        ->assertOk();

    Queue::assertPushed(
        SyncTaskReminderJob::class,
        fn(SyncTaskReminderJob $job) => $job->taskId === $task->id,
    );
});

test('task tanpa due date tidak mendispatch reminder saat status otomatis berubah', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project, ['assigned_to' => $owner->id]);
    $item    = createChecklistItem($task);

    $this->actingAs($owner)
        ->patchJson("/api/checklist-items/{$item->id}", ['is_done' => true])
        ->assertOk();

    Queue::assertNotPushed(SyncTaskReminderJob::class);
});
