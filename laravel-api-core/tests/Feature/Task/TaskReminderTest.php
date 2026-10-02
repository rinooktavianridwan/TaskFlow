<?php

use App\Enums\TaskStatus;
use App\Jobs\SyncTaskReminderJob;
use App\Models\User;
use App\Services\Grpc\NotificationGrpcClient;
use Illuminate\Support\Facades\Queue;

// ---------------------------------------------------------------- DISPATCH

test('membuat task dengan assignee dan due date mendispatch sinkronisasi reminder', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $this->actingAs($owner)
        ->postJson("/api/projects/{$project->id}/tasks", [
            'title'       => 'Task ber-reminder',
            'assigned_to' => $owner->id,
            'due_date'    => '2030-01-01 10:00:00',
        ])
        ->assertCreated();

    Queue::assertPushed(SyncTaskReminderJob::class, 1);
});

test('membuat task tanpa due date tidak mendispatch sinkronisasi reminder', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $this->actingAs($owner)
        ->postJson("/api/projects/{$project->id}/tasks", ['title' => 'Task biasa'])
        ->assertCreated();

    Queue::assertNotPushed(SyncTaskReminderJob::class);
});

test('mengubah status task mendispatch sinkronisasi reminder', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project, [
        'assigned_to' => $owner->id,
        'due_date'    => '2030-01-01 10:00:00',
    ]);

    $this->actingAs($owner)
        ->patchJson("/api/tasks/{$task->id}", ['status' => TaskStatus::Done->value])
        ->assertOk();

    Queue::assertPushed(
        SyncTaskReminderJob::class,
        fn(SyncTaskReminderJob $job) => $job->taskId === $task->id,
    );
});

test('menghapus project mendispatch pembatalan untuk task yang punya reminder saja', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);

    $withReminder = createTask($project, [
        'assigned_to' => $owner->id,
        'due_date'    => '2030-01-01 10:00:00',
    ]);
    createTask($project);

    $this->actingAs($owner)
        ->deleteJson("/api/projects/{$project->id}")
        ->assertNoContent();

    Queue::assertPushed(SyncTaskReminderJob::class, 1);
    Queue::assertPushed(
        SyncTaskReminderJob::class,
        fn(SyncTaskReminderJob $job) => $job->taskId === $withReminder->id,
    );
});

// ---------------------------------------------------------------- JOB

test('job menjadwalkan reminder untuk task dengan assignee dan due date', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner, ['name' => 'Proyek Demo']);
    $task    = createTask($project, [
        'title'       => 'Task ber-reminder',
        'assigned_to' => $owner->id,
        'due_date'    => '2030-01-01 10:00:00',
    ]);

    $expectedDueDate = $task->fresh()->due_date->copy()->utc()->format('Y-m-d\TH:i:s\Z');

    $client = $this->mock(NotificationGrpcClient::class);
    $client->shouldReceive('scheduleTaskReminder')
        ->once()
        ->with($task->id, 'Task ber-reminder', 'Proyek Demo', $owner->email, $expectedDueDate)
        ->andReturnTrue();
    $client->shouldNotReceive('cancelTaskReminder');

    (new SyncTaskReminderJob($task->id))->handle($client);
});

test('job membatalkan reminder jika task sudah selesai', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project, [
        'assigned_to' => $owner->id,
        'due_date'    => '2030-01-01 10:00:00',
        'status'      => TaskStatus::Done->value,
    ]);

    $client = $this->mock(NotificationGrpcClient::class);
    $client->shouldReceive('cancelTaskReminder')->once()->with($task->id)->andReturnTrue();
    $client->shouldNotReceive('scheduleTaskReminder');

    (new SyncTaskReminderJob($task->id))->handle($client);
});

test('job membatalkan reminder jika task tanpa assignee', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project, ['due_date' => '2030-01-01 10:00:00']);

    $client = $this->mock(NotificationGrpcClient::class);
    $client->shouldReceive('cancelTaskReminder')->once()->with($task->id)->andReturnTrue();
    $client->shouldNotReceive('scheduleTaskReminder');

    (new SyncTaskReminderJob($task->id))->handle($client);
});

test('job membatalkan reminder jika task tanpa due date', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $task    = createTask($project, ['assigned_to' => $owner->id]);

    $client = $this->mock(NotificationGrpcClient::class);
    $client->shouldReceive('cancelTaskReminder')->once()->with($task->id)->andReturnTrue();
    $client->shouldNotReceive('scheduleTaskReminder');

    (new SyncTaskReminderJob($task->id))->handle($client);
});

test('job membatalkan reminder jika task sudah dihapus', function () {
    $client = $this->mock(NotificationGrpcClient::class);
    $client->shouldReceive('cancelTaskReminder')->once()->with(999999)->andReturnTrue();
    $client->shouldNotReceive('scheduleTaskReminder');

    (new SyncTaskReminderJob(999999))->handle($client);
});
