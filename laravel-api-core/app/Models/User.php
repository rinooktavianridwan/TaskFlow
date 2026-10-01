<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * App\Models\User
 *
 * @property int                           $id
 * @property string                        $name
 * @property string                        $email
 * @property string|null                   $email_verified_at
 * @property string                        $password
 * @property string|null                   $remember_token
 * @property string|null                   $created_at
 * @property string|null                   $updated_at
 *
 * @property-read Collection|Task[]        $tasks
 * @property-read Collection|ProjectUser[] $projectUsers
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id'                => 'integer',
            'name'              => 'string',
            'email'             => 'string',
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'remember_token'    => 'string',
            'created_at'        => 'datetime',
            'updated_at'        => 'datetime',
        ];
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function projectUsers(): HasMany
    {
        return $this->hasMany(ProjectUser::class, 'user_id');
    }

    public function sendEmailVerificationNotification()
    {
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id'   => $this->getKey(),
                'hash' => sha1($this->getEmailForVerification()),
            ],
        );

        // TODO: gRPC Client akan menembak ke Go nanti.
        Log::info("Siap tembak gRPC! Verifikasi untuk {$this->email} ke URL: {$verificationUrl}");
    }
}
