<?php

use App\Enums\ProjectRole;
use App\Jobs\SendInvitationEmailJob;
use App\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

// Job email tidak boleh benar-benar jalan (itu memanggil gRPC), cukup kita cek ter-dispatch.
beforeEach(function () {
    Queue::fake();
});

// ---------------------------------------------------------------- STORE

test('owner bisa membuat undangan dan email dikirim lewat job', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $this->actingAs($owner)
        ->postJson("/api/projects/{$project->id}/invitations", [
            'email' => 'baru@example.com',
            'role'  => 'editor',
        ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'baru@example.com')
        ->assertJsonPath('data.role', 'editor')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonStructure(['data' => ['id', 'email', 'role', 'status', 'expires_at', 'created_at']])
        ->assertJsonMissingPath('data.token'); // token rahasia, hanya dikirim lewat email

    $invitation = ProjectInvitation::where('email', 'baru@example.com')->firstOrFail();

    expect($invitation->token)->toHaveLength(64)
        ->and($invitation->expires_at->between(now()->addDays(6), now()->addDays(8)))->toBeTrue();

    Queue::assertPushed(SendInvitationEmailJob::class, fn($job) => $job->email === 'baru@example.com'
        && $job->projectId === $project->id
        && $job->token === $invitation->token,
    );
});

test('email undangan disimpan dalam huruf kecil', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $this->actingAs($owner)
        ->postJson("/api/projects/{$project->id}/invitations", [
            'email' => '  Baru.User@Example.COM ',
            'role'  => 'viewer',
        ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'baru.user@example.com');

    $this->assertDatabaseHas('project_invitations', ['email' => 'baru.user@example.com']);
});

test('data undangan harus valid', function (array $payload, string $errorField) {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $this->actingAs($owner)
        ->postJson("/api/projects/{$project->id}/invitations", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errorField);

    Queue::assertNothingPushed();
})->with([
    'email kosong'       => [['role' => 'editor'], 'email'],
    'email tidak valid'  => [['email' => 'bukan-email', 'role' => 'editor'], 'email'],
    'role kosong'        => [['email' => 'a@example.com'], 'role'],
    'role tidak dikenal' => [['email' => 'a@example.com', 'role' => 'admin'], 'role'],
]);

test('tidak bisa mengundang orang yang sudah menjadi member', function () {
    $owner  = User::factory()->create();
    $member = User::factory()->create(['email' => 'budi@example.com']);

    $project = createProject($owner);
    addMember($project, $member, ProjectRole::Viewer);

    // huruf besar-kecil tidak boleh jadi celah
    $this->actingAs($owner)
        ->postJson("/api/projects/{$project->id}/invitations", [
            'email' => 'BUDI@Example.com',
            'role'  => 'editor',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    Queue::assertNothingPushed();
});

test('tidak bisa membuat undangan ganda selama masih pending', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    createInvitation($project, 'dobel@example.com');

    $this->actingAs($owner)
        ->postJson("/api/projects/{$project->id}/invitations", [
            'email' => 'dobel@example.com',
            'role'  => 'editor',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    expect(ProjectInvitation::count())->toBe(1);
    Queue::assertNothingPushed();
});

test('undangan pending yang sudah kedaluwarsa tidak menghalangi undangan baru', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    createInvitation($project, 'lama@example.com', ['expires_at' => now()->subDay()]);

    $this->actingAs($owner)
        ->postJson("/api/projects/{$project->id}/invitations", [
            'email' => 'lama@example.com',
            'role'  => 'editor',
        ])
        ->assertCreated();

    expect(ProjectInvitation::count())->toBe(2);
});

test('undangan yang sudah ditolak atau diterima tidak menghalangi undangan baru', function (string $status) {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    createInvitation($project, 'ulang@example.com', ['status' => $status]);

    $this->actingAs($owner)
        ->postJson("/api/projects/{$project->id}/invitations", [
            'email' => 'ulang@example.com',
            'role'  => 'editor',
        ])
        ->assertCreated();
})->with(['declined', 'accepted']);

// ---------------------------------------------------------------- AUTHORIZATION

test('hanya owner yang bisa mengelola undangan', function (string $role) {
    $owner  = User::factory()->create();
    $member = User::factory()->create();

    $project    = createProject($owner);
    $invitation = createInvitation($project, 'x@example.com');
    addMember($project, $member, ProjectRole::from($role));

    $this->actingAs($member);

    $this->getJson("/api/projects/{$project->id}/invitations")->assertForbidden();

    $this->postJson("/api/projects/{$project->id}/invitations", [
        'email' => 'y@example.com',
        'role'  => 'editor',
    ])->assertForbidden();

    $this->deleteJson("/api/projects/{$project->id}/invitations/{$invitation->id}")->assertForbidden();

    expect(ProjectInvitation::count())->toBe(1);
    Queue::assertNothingPushed();
})->with(['editor', 'viewer']);

test('non-member tidak bisa mengelola undangan', function () {
    $project  = createProject(User::factory()->create());
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->postJson("/api/projects/{$project->id}/invitations", [
            'email' => 'y@example.com',
            'role'  => 'editor',
        ])
        ->assertForbidden();
});

// ---------------------------------------------------------------- INDEX

test('owner melihat undangan project ini saja, terbaru di atas, tanpa token', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $otherProject = createProject(User::factory()->create());
    createInvitation($otherProject, 'lain@example.com');

    $first  = createInvitation($project, 'a@example.com');
    $second = createInvitation($project, 'b@example.com');

    $this->actingAs($owner)
        ->getJson("/api/projects/{$project->id}/invitations")
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items.0.id', $second->id)
        ->assertJsonPath('data.items.1.id', $first->id)
        ->assertJsonPath('data.meta.total', 2)
        ->assertJsonMissingPath('data.items.0.token')
        ->assertJsonStructure([
            'data' => ['items' => ['*' => ['id', 'email', 'role', 'status', 'expires_at', 'created_at']]],
        ]);
});

test('owner bisa memfilter undangan berdasarkan status', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $pending = createInvitation($project, 'a@example.com');
    createInvitation($project, 'b@example.com', ['status' => 'accepted']);
    createInvitation($project, 'c@example.com', ['status' => 'declined']);

    $this->actingAs($owner)
        ->getJson("/api/projects/{$project->id}/invitations?status=pending")
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $pending->id)
        ->assertJsonPath('data.meta.total', 1);
});

test('filter status undangan harus berupa status yang dikenal', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $this->actingAs($owner)
        ->getJson("/api/projects/{$project->id}/invitations?status=revoked")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

test('per_page di luar batas ditolak pada daftar undangan', function (int $perPage) {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $this->actingAs($owner)
        ->getJson("/api/projects/{$project->id}/invitations?per_page={$perPage}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');
})->with([0, 101]);

// ---------------------------------------------------------------- DESTROY

test('owner bisa menghapus undangan lewat id', function () {
    $owner      = User::factory()->create();
    $project    = createProject($owner);
    $invitation = createInvitation($project, 'hapus@example.com');

    $this->actingAs($owner)
        ->deleteJson("/api/projects/{$project->id}/invitations/{$invitation->id}")
        ->assertNoContent();

    $this->assertModelMissing($invitation);
});

test('owner tidak bisa menghapus undangan milik project lain', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $otherProject    = createProject(User::factory()->create());
    $otherInvitation = createInvitation($otherProject, 'lain@example.com');

    $this->actingAs($owner)
        ->deleteJson("/api/projects/{$project->id}/invitations/{$otherInvitation->id}")
        ->assertNotFound();

    $this->assertModelExists($otherInvitation);
});

test('undangan pending yang sudah kedaluwarsa tetap bisa dihapus', function () {
    $owner      = User::factory()->create();
    $project    = createProject($owner);
    $invitation = createInvitation($project, 'lama@example.com', ['expires_at' => now()->subDay()]);

    $this->actingAs($owner)
        ->deleteJson("/api/projects/{$project->id}/invitations/{$invitation->id}")
        ->assertNoContent();

    $this->assertModelMissing($invitation);
});

test('undangan yang sudah diterima atau ditolak tidak bisa dihapus', function (string $status) {
    $owner      = User::factory()->create();
    $project    = createProject($owner);
    $invitation = createInvitation($project, 'selesai@example.com', ['status' => $status]);

    $this->actingAs($owner)
        ->deleteJson("/api/projects/{$project->id}/invitations/{$invitation->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('invitation');

    $this->assertModelExists($invitation);
})->with(['accepted', 'declined']);

test('menghapus undangan pending membuat tokennya tidak bisa dipakai lagi', function () {
    $owner      = User::factory()->create();
    $invitee    = User::factory()->create(['email' => 'tamu@example.com']);
    $project    = createProject($owner);
    $invitation = createInvitation($project, 'tamu@example.com');
    $token      = $invitation->token;

    $this->actingAs($owner)
        ->deleteJson("/api/projects/{$project->id}/invitations/{$invitation->id}")
        ->assertNoContent();

    $this->actingAs($invitee)
        ->postJson("/api/invitations/{$token}/accept")
        ->assertNotFound();

    expect($project->projectUsers()->where('user_id', $invitee->id)->exists())->toBeFalse();
});
