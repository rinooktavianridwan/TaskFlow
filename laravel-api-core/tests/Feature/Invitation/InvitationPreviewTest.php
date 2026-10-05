<?php

use App\Models\User;
use Illuminate\Support\Carbon;

test('pratinjau undangan bisa dilihat tanpa login', function () {
    $project    = createProject(User::factory()->create(), ['name' => 'Proyek Alpha']);
    $invitation = createInvitation($project, 'budi@example.com', ['role' => 'viewer']);

    $response = $this->getJson("/api/invitations/{$invitation->token}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.project_name', 'Proyek Alpha')
        ->assertJsonPath('data.email', 'budi@example.com')
        ->assertJsonPath('data.role', 'viewer')
        ->assertJsonPath('data.status', 'pending');

    expect(array_keys($response->json('data')))
        ->toEqualCanonicalizing(['project_name', 'email', 'role', 'status', 'expires_at']);
});

test('pratinjau tidak membocorkan token, id, atau project_id', function () {
    $project    = createProject(User::factory()->create());
    $invitation = createInvitation($project, 'budi@example.com');

    $response = $this->getJson("/api/invitations/{$invitation->token}")->assertOk();

    $response->assertJsonMissingPath('data.token')
        ->assertJsonMissingPath('data.id')
        ->assertJsonMissingPath('data.project_id');

    expect($response->getContent())->not->toContain($invitation->token);
});

test('token yang tidak dikenal mengembalikan 404 dengan envelope standar', function () {
    $this->getJson('/api/invitations/token-palsu')
        ->assertNotFound()
        ->assertJson(['success' => false, 'message' => 'Resource not found.']);
});

test('pratinjau menampilkan status undangan yang sudah diproses', function (string $status) {
    $project    = createProject(User::factory()->create());
    $invitation = createInvitation($project, 'budi@example.com', ['status' => $status]);

    $this->getJson("/api/invitations/{$invitation->token}")
        ->assertOk()
        ->assertJsonPath('data.status', $status);
})->with(['accepted', 'declined']);

test('undangan kedaluwarsa tetap berstatus pending dan dibedakan lewat expires_at', function () {
    $project    = createProject(User::factory()->create());
    $invitation = createInvitation($project, 'budi@example.com', ['expires_at' => now()->subDay()]);

    $response = $this->getJson("/api/invitations/{$invitation->token}")
        ->assertOk()
        ->assertJsonPath('data.status', 'pending');

    expect(Carbon::parse($response->json('data.expires_at'))->isPast())->toBeTrue();
});

test('pratinjau tetap bisa dibuka saat login sebagai user lain', function () {
    $project    = createProject(User::factory()->create(), ['name' => 'Proyek Alpha']);
    $invitation = createInvitation($project, 'budi@example.com');

    $this->actingAs(User::factory()->create())
        ->getJson("/api/invitations/{$invitation->token}")
        ->assertOk()
        ->assertJsonPath('data.project_name', 'Proyek Alpha');
});

test('pratinjau dibatasi 30 request per menit', function () {
    $project    = createProject(User::factory()->create());
    $invitation = createInvitation($project, 'budi@example.com');

    for ($i = 0; $i < 30; $i++) {
        $this->getJson("/api/invitations/{$invitation->token}")->assertOk();
    }

    $this->getJson("/api/invitations/{$invitation->token}")->assertStatus(429);
});
