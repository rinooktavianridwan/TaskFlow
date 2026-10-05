<?php

use App\Models\User;

test('user melihat undangan pending miliknya beserta token', function () {
    $invitee    = User::factory()->create();
    $project    = createProject(User::factory()->create(), ['name' => 'Proyek Alpha']);
    $invitation = createInvitation($project, $invitee->email, ['role' => 'viewer']);

    $this->actingAs($invitee)->getJson('/api/invitations')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.items.0.token', $invitation->token)
        ->assertJsonPath('data.items.0.project_name', 'Proyek Alpha')
        ->assertJsonPath('data.items.0.role', 'viewer')
        ->assertJsonPath('data.items.0.status', 'pending')
        ->assertJsonMissingPath('data.items.0.id')
        ->assertJsonMissingPath('data.items.0.project_id');
});

test('daftar hanya memuat undangan pending, belum kedaluwarsa, dan milik user', function () {
    $invitee = User::factory()->create();
    $project = createProject(User::factory()->create());

    $active = createInvitation($project, $invitee->email);
    createInvitation($project, $invitee->email, ['status' => 'accepted']);
    createInvitation($project, $invitee->email, ['status' => 'declined']);
    createInvitation($project, $invitee->email, ['expires_at' => now()->subMinute()]);
    createInvitation($project, 'orang.lain@example.com');

    $this->actingAs($invitee)->getJson('/api/invitations')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.items.0.token', $active->token);
});

test('undangan tanpa expires_at dianggap tidak pernah kedaluwarsa', function () {
    $invitee = User::factory()->create();
    $project = createProject(User::factory()->create());
    createInvitation($project, $invitee->email, ['expires_at' => null]);

    $this->actingAs($invitee)->getJson('/api/invitations')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1);
});

test('daftar diurutkan dari undangan terbaru dan mendukung pagination', function () {
    $invitee = User::factory()->create();
    $project = createProject(User::factory()->create());
    createInvitation($project, $invitee->email);
    $newest = createInvitation($project, $invitee->email);

    $this->actingAs($invitee)->getJson('/api/invitations?per_page=1')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 2)
        ->assertJsonPath('data.meta.per_page', 1)
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.token', $newest->token);
});

test('per_page yang tidak valid ditolak', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/api/invitations?per_page=0')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');
});

test('guest tidak bisa melihat daftar undangan', function () {
    $this->getJson('/api/invitations')->assertUnauthorized();
});
