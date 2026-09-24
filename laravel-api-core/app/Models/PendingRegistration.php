<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'email', 'password', 'otp_code', 'attempts', 'expires_at'])]
#[Hidden(['password', 'otp_code'])]
class PendingRegistration extends Model
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'attempts'   => 'integer',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
