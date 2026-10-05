<?php

namespace App\Services\Grpc;

use Grpc\ChannelCredentials;
use Illuminate\Support\Facades\Log;
use Notification\V1\NotificationServiceClient;
use Notification\V1\SendInvitationEmailRequest;
use Notification\V1\SendPasswordResetEmailRequest;
use Notification\V1\SendVerificationEmailRequest;
use Notification\V1\CancelTaskReminderRequest;
use Notification\V1\ScheduleTaskReminderRequest;
use RuntimeException;

use const Grpc\STATUS_OK;

class NotificationGrpcClient
{
    private NotificationServiceClient $client;

    public function __construct()
    {
        $host = config('services.grpc.notification.host');
        $port = config('services.grpc.notification.port');

        $this->client = new NotificationServiceClient(
            "{$host}:{$port}",
            ['credentials' => ChannelCredentials::createInsecure()],
        );
    }

    /**
     * Kirim OTP verifikasi registrasi lewat go-notification-service.
     * Melempar exception kalau gagal, supaya Job bisa retry.
     */
    public function sendVerificationEmail(string $name, string $email, string $otpCode): bool
    {
        $request = new SendVerificationEmailRequest();
        $request->setUserId(0);
        $request->setName($name);
        $request->setEmail($email);
        $request->setToken($otpCode);

        [$response, $status] = $this->client
            ->SendVerificationEmail($request, [], ['timeout' => 10_000_000])
            ->wait();

        if ($status->code !== STATUS_OK) {
            Log::error('gRPC SendVerificationEmail gagal', [
                'email'  => $email,
                'code'   => $status->code,
                'detail' => $status->details,
            ]);

            throw new RuntimeException("gRPC call gagal: {$status->details}");
        }

        if (!$response->getSuccess()) {
            throw new RuntimeException("Email verifikasi gagal dikirim: {$response->getMessage()}");
        }

        return true;
    }

    public function sendInvitationEmail(int $projectId, string $projectName, string $email, string $acceptUrl): bool
    {
        $request = new SendInvitationEmailRequest();
        $request->setProjectId($projectId);
        $request->setProjectName($projectName);
        $request->setTargetEmail($email);
        $request->setAcceptUrl($acceptUrl);

        [$response, $status] = $this->client
            ->SendInvitationEmail($request, [], ['timeout' => 10_000_000])
            ->wait();

        if ($status->code !== STATUS_OK) {
            Log::error('gRPC SendInvitationEmail gagal', [
                'project_id' => $projectId,
                'email'      => $email,
                'code'       => $status->code,
                'detail'     => $status->details,
            ]);

            throw new RuntimeException("gRPC call gagal: {$status->details}");
        }

        if (!$response->getSuccess()) {
            throw new RuntimeException("Email undangan gagal dikirim: {$response->getMessage()}");
        }

        return true;
    }

    public function sendPasswordResetEmail(string $name, string $email, string $resetUrl): bool
    {
        $request = new SendPasswordResetEmailRequest();
        $request->setName($name);
        $request->setEmail($email);
        $request->setResetUrl($resetUrl);

        [$response, $status] = $this->client
            ->SendPasswordResetEmail($request, [], ['timeout' => 10_000_000])
            ->wait();

        if ($status->code !== STATUS_OK) {
            Log::error('gRPC SendPasswordResetEmail gagal', [
                'email'  => $email,
                'code'   => $status->code,
                'detail' => $status->details,
            ]);

            throw new RuntimeException("gRPC call gagal: {$status->details}");
        }

        if (!$response->getSuccess()) {
            throw new RuntimeException("Email reset password gagal dikirim: {$response->getMessage()}");
        }

        return true;
    }

    public function scheduleTaskReminder(
        int $taskId,
        string $taskTitle,
        string $projectName,
        string $assigneeEmail,
        string $dueDate,
    ): bool {
        $request = new ScheduleTaskReminderRequest();
        $request->setTaskId($taskId);
        $request->setTaskTitle($taskTitle);
        $request->setProjectName($projectName);
        $request->setAssigneeEmail($assigneeEmail);
        $request->setDueDate($dueDate);

        [$response, $status] = $this->client
            ->ScheduleTaskReminder($request, [], ['timeout' => 10_000_000])
            ->wait();

        if ($status->code !== STATUS_OK) {
            Log::error('gRPC ScheduleTaskReminder gagal', [
                'task_id' => $taskId,
                'code'    => $status->code,
                'detail'  => $status->details,
            ]);

            throw new RuntimeException("gRPC call gagal: {$status->details}");
        }

        if (!$response->getSuccess()) {
            throw new RuntimeException(
                "Reminder task gagal dijadwalkan: {$response->getMessage()}",
            );
        }

        return true;
    }

    public function cancelTaskReminder(int $taskId): bool
    {
        $request = new CancelTaskReminderRequest();
        $request->setTaskId($taskId);

        [$response, $status] = $this->client
            ->CancelTaskReminder($request, [], ['timeout' => 10_000_000])
            ->wait();

        if ($status->code !== STATUS_OK) {
            Log::error('gRPC CancelTaskReminder gagal', [
                'task_id' => $taskId,
                'code'    => $status->code,
                'detail'  => $status->details,
            ]);

            throw new RuntimeException("gRPC call gagal: {$status->details}");
        }

        if (!$response->getSuccess()) {
            throw new RuntimeException(
                "Reminder task gagal dibatalkan: {$response->getMessage()}",
            );
        }

        return true;
    }
}
