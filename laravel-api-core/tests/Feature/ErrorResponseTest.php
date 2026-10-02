<?php

use App\Models\User;

test('401: guest mendapat format error seragam', function () {
    $this->getJson('/api/projects')
        ->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Unauthenticated.');
});

test('403: akses ditolak mendapat format error seragam', function () {
    $project  = createProject(User::factory()->create());
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->getJson("/api/projects/{$project->id}")
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

test('404: resource tidak ada mendapat format seragam tanpa membocorkan nama model', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/projects/999999')
        ->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Resource not found.');
});

test('422: validasi gagal mendapat format error seragam', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/projects', [])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['success', 'message', 'errors' => ['name']]);
});
