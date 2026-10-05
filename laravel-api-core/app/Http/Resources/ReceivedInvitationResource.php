<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Undangan dari sisi penerima (user login). Memuat token karena accept/decline
 * memakai token sebagai route key. Aman: hanya dikembalikan ke pemilik email
 * undangan (email akun sudah terbukti lewat OTP) dan token itu memang sudah
 * dikirim ke emailnya.
 */
class ReceivedInvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token'        => $this->token,
            'project_name' => $this->project->name,
            'role'         => $this->role,
            'status'       => $this->status,
            'expires_at'   => $this->expires_at,
            'created_at'   => $this->created_at,
        ];
    }
}
