<?php

namespace App\Services\Grpc;

use Grpc\ChannelCredentials;
use Illuminate\Support\Facades\Log;
use Notification\V1\NotificationServiceClient;
use Notification\V1\SendInvitationEmailRequest;
use Notification\V1\SendVerificationEmailRequest;

class NotificationGrpcClient
{
    private NotificationServiceClient $client;

    public function __construct()
    {
        $host = config('services.grpc.notification.host');
        $port = config('services.grpc.notification.port');

        $this->client = new NotificationServiceClient(
            "{$host}:{$port}",
            ['credentials' => ChannelCredentials::createInsecure()]
        );
    }

    /**
     * Kirim OTP verifikasi registrasi lewat go-notification-service.
     * Melempar exception kalau gagal, supaya Job bisa retry.
     */
    public function sendVerificationEmail(string $name, string $email, string $otpCode): bool
    {
        $request = new SendVerificationEmailRequest();
        $request->setUserId(0); // belum ada user asli saat tahap ini
        $request->setName($name);
        $request->setEmail($email);
        $request->setToken($otpCode);

        [$response, $status] = $this->client->SendVerificationEmail($request)->wait();

        if ($status->code !== \Grpc\STATUS_OK) {
            Log::error('gRPC SendVerificationEmail gagal', [
                'email'  => $email,
                'code'   => $status->code,
                'detail' => $status->details,
            ]);

            throw new \RuntimeException("gRPC call gagal: {$status->details}");
        }

        return $response->getSuccess();
    }

    public function sendInvitationEmail(int $projectId, string $projectName, string $email, string $token): bool
    {
        $request = new SendInvitationEmailRequest();
        $request->setProjectId($projectId);
        $request->setProjectName($projectName);
        $request->setTargetEmail($email);
        $request->setToken($token);

        [$response, $status] = $this->client->SendInvitationEmail($request)->wait();

        if ($status->code !== \Grpc\STATUS_OK) {
            Log::error('gRPC SendInvitationEmail gagal', [
                'project_id' => $projectId,
                'email'      => $email,
                'code'       => $status->code,
                'detail'     => $status->details,
            ]);

            throw new \RuntimeException("gRPC call gagal: {$status->details}");
        }

        if (! $response->getSuccess()) {
            throw new \RuntimeException("Email undangan gagal dikirim: {$response->getMessage()}");
        }

        return true;
    }
}
