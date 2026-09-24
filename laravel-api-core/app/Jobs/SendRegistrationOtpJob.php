<?php

namespace App\Jobs;

use App\Services\Grpc\NotificationGrpcClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendRegistrationOtpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;

    public function __construct(
        public string $name,
        public string $email,
        public string $otpCode,
    ) {}

    public function handle(NotificationGrpcClient $client): void
    {
        $client->sendVerificationEmail($this->name, $this->email, $this->otpCode);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Gagal mengirim OTP registrasi setelah semua percobaan', [
            'email' => $this->email,
            'error' => $exception->getMessage(),
        ]);
    }
}
