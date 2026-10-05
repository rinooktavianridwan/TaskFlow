<?php

namespace App\Jobs;

use App\Services\Grpc\NotificationGrpcClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendPasswordResetEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 5;

    public function __construct(
        public string $name,
        public string $email,
        public string $token,
    ) {
    }

    public function handle(NotificationGrpcClient $client): void
    {
        $resetUrl = rtrim(config('app.frontend_url'), '/')
            . '/password-reset/' . rawurlencode($this->token)
            . '?email=' . urlencode($this->email);

        $client->sendPasswordResetEmail($this->name, $this->email, $resetUrl);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Gagal mengirim email reset password setelah semua percobaan', [
            'email' => $this->email,
            'error' => $exception->getMessage(),
        ]);
    }
}
