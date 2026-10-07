<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * App\Models\User
 *
 * @property int                               $id
 * @property string                            $name
 * @property string                            $email
 * @property Carbon|null                       $email_verified_at
 * @property string                            $password
 * @property string                            $timezone
 * @property string|null                       $remember_token
 * @property Carbon|null                       $created_at
 * @property Carbon|null                       $updated_at
 *
 * @property-read Collection<int, Task>        $tasks
 * @property-read Collection<int, ProjectUser> $projectUsers
 */
#[Fillable(['name', 'email', 'password', 'timezone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = [
        'timezone' => 'UTC',
    ];

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
            'timezone'          => 'string',
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
}
