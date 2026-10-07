<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// ---------------------------------------------------------------- PROFIL

test('user bisa mengubah nama dan timezone, response berisi profil terbaru', function () {
    $user = User::factory()->create(['name' => 'Lama']);

    $this->actingAs($user)
        ->patchJson('/api/profile', ['name' => 'Baru', 'timezone' => 'Asia/Jakarta'])
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.name', 'Baru')
        ->assertJsonPath('data.timezone', 'Asia/Jakarta');

    $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Baru', 'timezone' => 'Asia/Jakarta']);
});

test('nama atau timezone saja sudah cukup untuk update', function () {
    $user = User::factory()->create(['name' => 'Tetap']);

    $this->actingAs($user)
        ->patchJson('/api/profile', ['timezone' => 'Asia/Makassar'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Tetap')
        ->assertJsonPath('data.timezone', 'Asia/Makassar');

    $this->actingAs($user)
        ->patchJson('/api/profile', ['name' => 'Nama Baru'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Nama Baru')
        ->assertJsonPath('data.timezone', 'Asia/Makassar');
});

test('email tidak bisa diubah lewat profil', function () {
    $user = User::factory()->create(['email' => 'asli@example.com']);

    $this->actingAs($user)
        ->patchJson('/api/profile', ['name' => 'Baru', 'email' => 'lain@example.com'])
        ->assertOk()
        ->assertJsonPath('data.email', 'asli@example.com');

    $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'asli@example.com']);
});

test('payload profil yang tidak valid ditolak', function (array $payload, array $fields) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/api/profile', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($fields);
})->with([
    'body kosong'            => [[], ['name', 'timezone']],
    'nama kosong'            => [['name' => ''], ['name']],
    'nama terlalu panjang'   => [['name' => str_repeat('a', 256)], ['name']],
    'timezone tidak dikenal' => [['timezone' => 'Bukan/Zona'], ['timezone']],
]);

test('user baru memiliki timezone default UTC', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('timezone', 'UTC');
});

test('guest tidak bisa mengakses endpoint profil', function () {
    $this->patchJson('/api/profile', ['name' => 'X'])->assertUnauthorized();
    $this->putJson('/api/profile/password', [])->assertUnauthorized();
});

// ---------------------------------------------------------------- PASSWORD

test('password bisa diganti dengan password lama yang benar', function () {
    $user = User::factory()->create(['password' => 'password-lama-123']);

    $this->actingAs($user)
        ->putJson('/api/profile/password', [
            'current_password'      => 'password-lama-123',
            'password'              => 'password-baru-456',
            'password_confirmation' => 'password-baru-456',
        ])
        ->assertNoContent();

    expect(Hash::check('password-baru-456', $user->fresh()->password))->toBeTrue();
});

test('password lama yang salah ditolak dan password tidak berubah', function () {
    $user = User::factory()->create(['password' => 'password-lama-123']);

    $this->actingAs($user)
        ->putJson('/api/profile/password', [
            'current_password'      => 'salah-total',
            'password'              => 'password-baru-456',
            'password_confirmation' => 'password-baru-456',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    expect(Hash::check('password-lama-123', $user->fresh()->password))->toBeTrue();
});

test('konfirmasi tidak cocok, password sama dengan yang lama, dan password pendek ditolak', function (array $payload) {
    $user = User::factory()->create(['password' => 'password-lama-123']);

    $this->actingAs($user)
        ->putJson('/api/profile/password', array_merge(['current_password' => 'password-lama-123'], $payload))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');
})->with([
    'konfirmasi tidak cocok' => [['password' => 'password-baru-456', 'password_confirmation' => 'beda']],
    'sama dengan yang lama'  => [['password' => 'password-lama-123', 'password_confirmation' => 'password-lama-123']],
    'terlalu pendek'         => [['password' => 'abc', 'password_confirmation' => 'abc']],
]);

test('ganti password dibatasi 6 percobaan per menit', function () {
    $user = User::factory()->create(['password' => 'password-lama-123']);

    for ($i = 0; $i < 6; $i++) {
        $this->actingAs($user)
            ->putJson('/api/profile/password', ['current_password' => 'salah'])
            ->assertUnprocessable();
    }

    $this->actingAs($user)
        ->putJson('/api/profile/password', ['current_password' => 'salah'])
        ->assertStatus(429);
});
