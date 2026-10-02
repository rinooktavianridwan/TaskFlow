<?php

use App\Enums\ProjectRole;
use App\Models\ProjectUser;
use App\Models\User;

// ---------------------------------------------------------------- ACCEPT

test('user yang diundang bisa menerima undangan dan menjadi member', function () {
    $invitee    = User::factory()->create();
    $project    = createProject(User::factory()->create());
    $invitation = createInvitation($project, $invitee->email, ['role' => 'editor']);

    $this->actingAs($invitee)
        ->postJson("/api/invitations/{$invitation->token}/accept")
        ->assertNoContent();

    $this->assertDatabaseHas('project_user', [
        'project_id' => $project->id,
        'user_id'    => $invitee->id,
        'role'       => 'editor',
    ]);
    expect($invitation->fresh()->status)->toBe('accepted');
});

test('user dengan email berbeda tidak bisa menerima undangan', function () {
    $stranger   = User::factory()->create();
    $project    = createProject(User::factory()->create());
    $invitation = createInvitation($project, 'orang.lain@example.com');

    $this->actingAs($stranger)
        ->postJson("/api/invitations/{$invitation->token}/accept")
        ->assertForbidden();

    $this->assertDatabaseMissing('project_user', ['user_id' => $stranger->id]);
    expect($invitation->fresh()->status)->toBe('pending');
});

test('undangan kedaluwarsa tidak bisa diterima', function () {
    $invitee    = User::factory()->create();
    $project    = createProject(User::factory()->create());
    $invitation = createInvitation($project, $invitee->email, ['expires_at' => now()->subMinute()]);

    $this->actingAs($invitee)
        ->postJson("/api/invitations/{$invitation->token}/accept")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('invitation');

    $this->assertDatabaseMissing('project_user', ['user_id' => $invitee->id]);
    expect($invitation->fresh()->status)->toBe('pending');
});

test('undangan yang sudah diterima atau ditolak tidak bisa diterima lagi', function (string $status) {
    $invitee    = User::factory()->create();
    $project    = createProject(User::factory()->create());
    $invitation = createInvitation($project, $invitee->email, ['status' => $status]);

    $this->actingAs($invitee)
        ->postJson("/api/invitations/{$invitation->token}/accept")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('invitation');

    $this->assertDatabaseMissing('project_user', ['user_id' => $invitee->id]);
})->with(['accepted', 'declined']);

test('menerima undangan dua kali tidak membuat membership ganda', function () {
    $invitee    = User::factory()->create();
    $project    = createProject(User::factory()->create());
    $invitation = createInvitation($project, $invitee->email);

    $this->actingAs($invitee)->postJson("/api/invitations/{$invitation->token}/accept")->assertNoContent();
    $this->actingAs($invitee)->postJson("/api/invitations/{$invitation->token}/accept")->assertUnprocessable();

    expect(ProjectUser::where('project_id', $project->id)->where('user_id', $invitee->id)->count())->toBe(1);
});

test('user yang sudah menjadi member tidak bisa menerima undangan', function () {
    $invitee    = User::factory()->create();
    $project    = createProject(User::factory()->create());
    addMember($project, $invitee, ProjectRole::Viewer);
    $invitation = createInvitation($project, $invitee->email, ['role' => 'owner']);

    $this->actingAs($invitee)
        ->postJson("/api/invitations/{$invitation->token}/accept")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('invitation');

    // role tidak boleh naik lewat jalur ini
    $this->assertDatabaseHas('project_user', ['user_id' => $invitee->id, 'role' => 'viewer']);
});

test('token yang tidak dikenal mengembalikan 404', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/invitations/token-palsu/accept')->assertNotFound();
});

test('guest tidak bisa menerima undangan', function () {
    $project    = createProject(User::factory()->create());
    $invitation = createInvitation($project, 'a@example.com');

    $this->postJson("/api/invitations/{$invitation->token}/accept")->assertUnauthorized();
});

// ---------------------------------------------------------------- DECLINE

test('user yang diundang bisa menolak undangan', function () {
    $invitee    = User::factory()->create();
    $project    = createProject(User::factory()->create());
    $invitation = createInvitation($project, $invitee->email);

    $this->actingAs($invitee)
        ->postJson("/api/invitations/{$invitation->token}/decline")
        ->assertNoContent();

    expect($invitation->fresh()->status)->toBe('declined');
    $this->assertDatabaseMissing('project_user', ['user_id' => $invitee->id]);
});

test('user dengan email berbeda tidak bisa menolak undangan orang lain', function () {
    $stranger   = User::factory()->create();
    $project    = createProject(User::factory()->create());
    $invitation = createInvitation($project, 'orang.lain@example.com');

    $this->actingAs($stranger)
        ->postJson("/api/invitations/{$invitation->token}/decline")
        ->assertForbidden();

    expect($invitation->fresh()->status)->toBe('pending');
});

test('undangan kedaluwarsa atau yang sudah diproses tidak bisa ditolak', function (array $overrides) {
    $invitee    = User::factory()->create();
    $project    = createProject(User::factory()->create());
    $invitation = createInvitation($project, $invitee->email, $overrides);

    $this->actingAs($invitee)
        ->postJson("/api/invitations/{$invitation->token}/decline")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('invitation');
})->with([
    'kedaluwarsa'    => [['expires_at' => '2000-01-01 00:00:00']],
    'sudah diterima' => [['status' => 'accepted']],
    'sudah ditolak'  => [['status' => 'declined']],
]);

test('undangan yang sudah ditolak tidak bisa diterima setelahnya', function () {
    $invitee    = User::factory()->create();
    $project    = createProject(User::factory()->create());
    $invitation = createInvitation($project, $invitee->email);

    $this->actingAs($invitee)->postJson("/api/invitations/{$invitation->token}/decline")->assertNoContent();
    $this->actingAs($invitee)->postJson("/api/invitations/{$invitation->token}/accept")->assertUnprocessable();

    $this->assertDatabaseMissing('project_user', ['user_id' => $invitee->id]);
});
