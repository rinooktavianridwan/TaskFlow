<?php

use App\Enums\TaskStatus;
use App\Models\User;
use App\Models\Project;

test('project tanpa task memiliki progress 0', function () {
    $owner = User::factory()->create();
    createProject($owner);

    $this->actingAs($owner)
        ->getJson('/api/projects')
        ->assertOk()
        ->assertJsonPath('data.items.0.progress', 0);
});

test('progress project adalah rata-rata progress semua task', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    createTask($project, ['status' => TaskStatus::Done->value]);

    $half = createTask($project, ['status' => TaskStatus::InProgress->value]);
    createChecklistItem($half, ['is_done' => true, 'position' => 1]);
    createChecklistItem($half, ['position' => 2]);

    createTask($project);

    // (100 + 50 + 0) / 3 = 50
    $this->actingAs($owner)
        ->getJson('/api/projects')
        ->assertOk()
        ->assertJsonPath('data.items.0.progress', 50);

    $this->actingAs($owner)
        ->getJson("/api/projects/{$project->id}")
        ->assertOk()
        ->assertJsonPath('data.progress', 50);
});

test('progress project dibulatkan ke bilangan bulat', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    createTask($project, ['status' => TaskStatus::Done->value]);
    createTask($project, ['status' => TaskStatus::Done->value]);
    createTask($project);

    // (100 + 100 + 0) / 3 = 66,67 -> 67
    $this->actingAs($owner)
        ->getJson("/api/projects/{$project->id}")
        ->assertOk()
        ->assertJsonPath('data.progress', 67);
});

test('task done dihitung 100 walau checklist-nya belum tercentang', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $task = createTask($project, ['status' => TaskStatus::Done->value]);
    createChecklistItem($task);

    $this->actingAs($owner)
        ->getJson("/api/projects/{$project->id}")
        ->assertOk()
        ->assertJsonPath('data.progress', 100);
});

test('progress tidak tercampur antar project', function () {
    $owner = User::factory()->create();

    $finished = createProject($owner, ['name' => 'Selesai']);
    createTask($finished, ['status' => TaskStatus::Done->value]);

    $untouched = createProject($owner, ['name' => 'Belum']);
    createTask($untouched);

    $response = $this->actingAs($owner)->getJson('/api/projects')->assertOk();

    $progress = collect($response->json('data.items'))->pluck('progress', 'id');

    expect($progress[$finished->id])->toBe(100)
        ->and($progress[$untouched->id])->toBe(0);
});

test('project baru dan hasil update tetap menyertakan progress', function () {
    $owner = User::factory()->create();

    $projectId = $this->actingAs($owner)
        ->postJson('/api/projects', ['name' => 'Baru'])
        ->assertCreated()
        ->assertJsonPath('data.progress', 0)
        ->json('data.id');

    $project = Project::query()->findOrFail($projectId);
    createTask($project, ['status' => TaskStatus::Done->value]);

    $this->actingAs($owner)
        ->patchJson("/api/projects/{$projectId}", ['name' => 'Diubah'])
        ->assertOk()
        ->assertJsonPath('data.progress', 100);
});
