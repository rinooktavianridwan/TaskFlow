<?php

use App\Enums\ActivityAction;
use App\Models\User;

// ---------------------------------------------------------------- ISI RANGKUMAN

test('rangkuman hari ini menampilkan item yang diselesaikan beserta progres checklist terkini', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner, ['name' => 'Proyek Harian']);
    $task    = createTask($project, ['title' => 'Fitur X']);

    $items = collect(range(1, 4))->map(
        fn(int $i) => createChecklistItem($task, ['title' => "Opsi {$i}", 'position' => $i]),
    );

    foreach ([0, 1] as $index) {
        $this->actingAs($owner)
            ->patchJson("/api/checklist-items/{$items[$index]->id}", ['is_done' => true])
            ->assertOk();
    }

    $this->actingAs($owner)
        ->getJson('/api/me/daily-summary')
        ->assertOk()
        ->assertJsonCount(1, 'data.days')
        // 2 item dicentang + 1 perubahan status otomatis (todo -> in_progress)
        ->assertJsonPath('data.days.0.total_events', 3)
        ->assertJsonPath('data.days.0.projects.0.project_name', 'Proyek Harian')
        ->assertJsonPath('data.days.0.projects.0.tasks.0.task_title', 'Fitur X')
        ->assertJsonPath('data.days.0.projects.0.tasks.0.task_exists', true)
        ->assertJsonPath('data.days.0.projects.0.tasks.0.checklist.total', 4)
        ->assertJsonPath('data.days.0.projects.0.tasks.0.checklist.done', 2)
        ->assertJsonPath('data.days.0.projects.0.tasks.0.completed_items', ['Opsi 1', 'Opsi 2']);
});

test('item yang dicentang lalu dibuka kembali tidak dihitung sebagai selesai', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project);
    $item    = createChecklistItem($task, ['title' => 'Batal']);
    createChecklistItem($task, ['title' => 'Lain', 'position' => 2]);

    $this->actingAs($owner)->patchJson("/api/checklist-items/{$item->id}", ['is_done' => true])->assertOk();
    $this->actingAs($owner)->patchJson("/api/checklist-items/{$item->id}", ['is_done' => false])->assertOk();

    $this->actingAs($owner)
        ->getJson('/api/me/daily-summary')
        ->assertOk()
        ->assertJsonPath('data.days.0.projects.0.tasks.0.completed_items', []);
});

test('event tingkat project muncul di daftar events project', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $this->actingAs($owner)
        ->postJson("/api/projects/{$project->id}/invitations", ['email' => 'baru@example.com', 'role' => 'viewer'])
        ->assertCreated();

    $this->actingAs($owner)
        ->getJson('/api/me/daily-summary')
        ->assertOk()
        ->assertJsonPath('data.days.0.projects.0.events.0.action', 'invitation_sent')
        ->assertJsonCount(0, 'data.days.0.projects.0.tasks');
});

test('task yang sudah dihapus tetap muncul dengan judul dari snapshot', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project, ['title' => 'Task sementara']);

    $this->actingAs($owner)->deleteJson("/api/tasks/{$task->id}")->assertNoContent();

    $this->actingAs($owner)
        ->getJson('/api/me/daily-summary')
        ->assertOk()
        ->assertJsonPath('data.days.0.projects.0.tasks.0.task_title', 'Task sementara')
        ->assertJsonPath('data.days.0.projects.0.tasks.0.task_exists', false)
        ->assertJsonPath('data.days.0.projects.0.tasks.0.checklist', null);
});

test('rangkuman hanya memuat aktivitas milik user yang login', function () {
    $owner   = User::factory()->create();
    $editor  = User::factory()->create();
    $project = createProject($owner);
    addMember($project, $editor, \App\Enums\ProjectRole::Editor);
    $task = createTask($project);

    createTaskActivity($task, $editor);

    $this->actingAs($owner)
        ->getJson('/api/me/daily-summary')
        ->assertOk()
        ->assertJsonCount(0, 'data.days');
});

test('hari diurutkan dari terbaru dan rentang tanggal dihormati', function () {
    $user    = User::factory()->create();
    $project = createProject($user);
    $task    = createTask($project);

    createTaskActivity($task, $user)->forceFill(['created_at' => '2026-10-05 12:00:00'])->save();
    createTaskActivity($task, $user)->forceFill(['created_at' => '2026-10-07 12:00:00'])->save();
    createTaskActivity($task, $user)->forceFill(['created_at' => '2026-10-20 12:00:00'])->save();

    $this->actingAs($user)
        ->getJson('/api/me/daily-summary?from=2026-10-05&to=2026-10-07')
        ->assertOk()
        ->assertJsonCount(2, 'data.days')
        ->assertJsonPath('data.days.0.date', '2026-10-07')
        ->assertJsonPath('data.days.1.date', '2026-10-05')
        ->assertJsonPath('data.from', '2026-10-05')
        ->assertJsonPath('data.to', '2026-10-07');
});

test('hanya from yang diisi berarti satu hari saja', function () {
    $user    = User::factory()->create();
    $project = createProject($user);
    $task    = createTask($project);

    createTaskActivity($task, $user)->forceFill(['created_at' => '2026-10-05 12:00:00'])->save();
    createTaskActivity($task, $user)->forceFill(['created_at' => '2026-10-06 12:00:00'])->save();

    $this->actingAs($user)
        ->getJson('/api/me/daily-summary?from=2026-10-05')
        ->assertOk()
        ->assertJsonCount(1, 'data.days')
        ->assertJsonPath('data.to', '2026-10-05');
});

// ---------------------------------------------------------------- ZONA WAKTU

test('hari dikelompokkan menurut zona waktu user', function () {
    $user    = User::factory()->create(['timezone' => 'Asia/Jakarta']);
    $project = createProject($user);
    $task    = createTask($project);

    // 18:00 UTC tanggal 6 = 01:00 WIB tanggal 7
    createTaskActivity($task, $user)->forceFill(['created_at' => '2026-10-06 18:00:00'])->save();

    $this->actingAs($user)
        ->getJson('/api/me/daily-summary?from=2026-10-07&to=2026-10-07')
        ->assertOk()
        ->assertJsonPath('data.timezone', 'Asia/Jakarta')
        ->assertJsonCount(1, 'data.days')
        ->assertJsonPath('data.days.0.date', '2026-10-07');

    $this->actingAs($user)
        ->getJson('/api/me/daily-summary?from=2026-10-06&to=2026-10-06')
        ->assertOk()
        ->assertJsonCount(0, 'data.days');
});

// ---------------------------------------------------------------- VALIDASI & AKSES

test('parameter tanggal yang tidak valid ditolak', function (string $query, string $field) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson("/api/me/daily-summary?{$query}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'to tanpa from'              => ['to=2026-10-07', 'from'],
    'format from salah'          => ['from=07-10-2026', 'from'],
    'to sebelum from'            => ['from=2026-10-07&to=2026-10-06', 'to'],
    'rentang lebih dari 31 hari' => ['from=2026-09-01&to=2026-10-02', 'to'],
]);

test('rentang tepat 31 hari masih diterima', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/me/daily-summary?from=2026-09-01&to=2026-10-01')
        ->assertOk();
});

test('guest tidak bisa melihat rangkuman harian', function () {
    $this->getJson('/api/me/daily-summary')->assertUnauthorized();
});
