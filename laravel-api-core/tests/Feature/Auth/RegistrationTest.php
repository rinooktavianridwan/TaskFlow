<?php

use App\Jobs\SendRegistrationOtpJob;

test('register stores pending registration and dispatches otp job', function () {
    Queue::fake();

    $this->postJson('/register', [
        'name' => 'Test User', 'email' => 'test@example.com',
        'password' => 'Password123!', 'password_confirmation' => 'Password123!',
    ])->assertStatus(202);

    $this->assertDatabaseHas('pending_registrations', ['email' => 'test@example.com']);
    Queue::assertPushed(SendRegistrationOtpJob::class);
});
