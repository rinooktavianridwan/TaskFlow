<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pratinjau publik undangan (tanpa login). Sengaja TIDAK memuat token, id, atau project_id.
 */
class InvitationPreviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'project_name' => $this->project->name,
            'email'        => $this->email,
            'role'         => $this->role,
            'status'       => $this->status,
            'expires_at'   => $this->expires_at,
        ];
    }
}
