<?php

use App\Jobs\SendPasswordResetEmailJob;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;

// ---------------------------------------------------------------- FORGOT PASSWORD

test('permintaan reset password mengantre email untuk user terdaftar', function () {
    $user = User::factory()->create();

    $this->postJson('/forgot-password', ['email' => $user->email])
        ->assertOk()
        ->assertJsonStructure(['status']);

    Queue::assertPushed(SendPasswordResetEmailJob::class, function (SendPasswordResetEmailJob $job) use ($user) {
        return $job->email === $user->email
            && $job->name === $user->name
            && $job->token !== '';
    });
});

test('email tidak terdaftar mendapat respons yang sama dan tidak mengantre apa pun', function () {
    $user = User::factory()->create();

    $found   = $this->postJson('/forgot-password', ['email' => $user->email]);
    $missing = $this->postJson('/forgot-password', ['email' => 'tidak.ada@example.com']);

    expect($missing->status())->toBe($found->status());
    expect($missing->json())->toBe($found->json());

    Queue::assertPushed(SendPasswordResetEmailJob::class, 1);
});

test('permintaan berulang dalam 60 detik tidak mengantre email kedua', function () {
    $user = User::factory()->create();

    $this->postJson('/forgot-password', ['email' => $user->email])->assertOk();
    $this->postJson('/forgot-password', ['email' => $user->email])->assertOk();

    Queue::assertPushed(SendPasswordResetEmailJob::class, 1);
});

test('format email yang salah ditolak', function () {
    $this->postJson('/forgot-password', ['email' => 'bukan-email'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->postJson('/forgot-password', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('forgot-password dibatasi 5 request per menit', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/forgot-password', ['email' => 'tidak.ada@example.com'])->assertOk();
    }

    $this->postJson('/forgot-password', ['email' => 'tidak.ada@example.com'])->assertStatus(429);
});

// ---------------------------------------------------------------- RESET PASSWORD

test('password bisa diganti dengan token yang valid', function () {
    $user  = User::factory()->create();
    $token = Password::broker()->createToken($user);

    $this->postJson('/reset-password', [
        'token'                 => $token,
        'email'                 => $user->email,
        'password'              => 'NewPassword123',
        'password_confirmation' => 'NewPassword123',
    ])->assertOk()->assertJsonStructure(['status']);

    expect(Hash::check('NewPassword123', $user->fresh()->password))->toBeTrue();
});

test('token yang salah ditolak dan password tidak berubah', function () {
    $user        = User::factory()->create();
    $oldPassword = $user->password;

    $this->postJson('/reset-password', [
        'token'                 => 'token-palsu',
        'email'                 => $user->email,
        'password'              => 'NewPassword123',
        'password_confirmation' => 'NewPassword123',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    expect($user->fresh()->password)->toBe($oldPassword);
});

test('token tidak bisa dipakai dua kali', function () {
    $user  = User::factory()->create();
    $token = Password::broker()->createToken($user);

    $payload = [
        'token'                 => $token,
        'email'                 => $user->email,
        'password'              => 'NewPassword123',
        'password_confirmation' => 'NewPassword123',
    ];

    $this->postJson('/reset-password', $payload)->assertOk();
    $this->postJson('/reset-password', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('konfirmasi password yang tidak cocok ditolak', function () {
    $user  = User::factory()->create();
    $token = Password::broker()->createToken($user);

    $this->postJson('/reset-password', [
        'token'                 => $token,
        'email'                 => $user->email,
        'password'              => 'NewPassword123',
        'password_confirmation' => 'Berbeda12345',
    ])->assertUnprocessable()->assertJsonValidationErrors('password');
});
