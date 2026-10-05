<?php

use App\Jobs\SendPasswordResetEmailJob;
use App\Services\Grpc\NotificationGrpcClient;

test('tautan reset memuat token dan email yang sudah di-encode', function (string $frontendUrl) {
    config(['app.frontend_url' => $frontendUrl]);

    $client = Mockery::mock(NotificationGrpcClient::class);
    $client->shouldReceive('sendPasswordResetEmail')
        ->once()
        ->with(
            'Budi',
            'budi+kerja@example.com',
            'http://frontend.test/password-reset/abc%2F123?email=budi%2Bkerja%40example.com',
        )
        ->andReturnTrue();

    (new SendPasswordResetEmailJob('Budi', 'budi+kerja@example.com', 'abc/123'))->handle($client);
})->with([
    'tanpa slash akhir'  => 'http://frontend.test',
    'dengan slash akhir' => 'http://frontend.test/',
]);
