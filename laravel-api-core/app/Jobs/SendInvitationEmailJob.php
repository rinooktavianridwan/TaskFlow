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

class SendInvitationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 5;

    public function __construct(
        public int $projectId,
        public string $projectName,
        public string $email,
        public string $token,
    ) {
    }

    public function handle(NotificationGrpcClient $client): void
    {
        $client->sendInvitationEmail($this->projectId, $this->projectName, $this->email, $this->token);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Gagal mengirim email undangan setelah semua percobaan', [
            'project_id' => $this->projectId,
            'email'      => $this->email,
            'error'      => $exception->getMessage(),
        ]);
    }
}
