<?php

use App\Jobs\SendInvitationEmailJob;
use App\Services\Grpc\NotificationGrpcClient;

test('tautan undangan memuat token dan memakai frontend_url', function (string $frontendUrl) {
    config(['app.frontend_url' => $frontendUrl]);

    $client = Mockery::mock(NotificationGrpcClient::class);
    $client->shouldReceive('sendInvitationEmail')
        ->once()
        ->with(7, 'Proyek Alpha', 'budi@example.com', 'http://frontend.test/invitations/abc123')
        ->andReturnTrue();

    (new SendInvitationEmailJob(7, 'Proyek Alpha', 'budi@example.com', 'abc123'))->handle($client);
})->with([
    'tanpa slash akhir'  => 'http://frontend.test',
    'dengan slash akhir' => 'http://frontend.test/',
]);

test('token di-encode agar aman dipakai di URL', function () {
    config(['app.frontend_url' => 'http://frontend.test']);

    $client = Mockery::mock(NotificationGrpcClient::class);
    $client->shouldReceive('sendInvitationEmail')
        ->once()
        ->with(7, 'Proyek Alpha', 'budi@example.com', 'http://frontend.test/invitations/a%2Fb%20c')
        ->andReturnTrue();

    (new SendInvitationEmailJob(7, 'Proyek Alpha', 'budi@example.com', 'a/b c'))->handle($client);
});
