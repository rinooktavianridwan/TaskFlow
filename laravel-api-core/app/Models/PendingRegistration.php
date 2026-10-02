<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * App\Models\PendingRegistration
 *
 * @property int                           $id
 * @property string                        $name
 * @property string                        $email
 * @property string                        $password
 * @property string                        $otp_code
 * @property int                           $attempt
 * @property Carbon                        $expired_at
 * @property Carbon|null                   $created_at
 * @property Carbon|null                   $updated_at
 *
 * @property-read Collection|Task[]        $tasks
 * @property-read Collection|ProjectUser[] $projectUsers
 */
class PendingRegistration extends Model
{
    protected $table = 'pending_registrations';

    protected $fillable = [
        'name',
        'email',
        'password',
        'otp_code',
        'attempts',
        'expires_at',
    ];

    protected $hidden = [
        'password',
        'otp_code',
    ];

    protected $casts = [
        'id'         => 'integer',
        'name'       => 'string',
        'email'      => 'string',
        'password'   => 'string',
        'otp_code'   => 'string',
        'attempts'   => 'integer',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
