<?php

namespace App\Models;

use App\Enums\ProjectRole;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * App\Models\Project
 *
 * @property int                                     $id
 * @property string                                  $name
 * @property string|null                             $description
 * @property Carbon|null                             $created_at
 * @property Carbon|null                             $updated_at
 *
 * @property-read Collection<int, Task>              $tasks
 * @property-read Collection<int, ProjectUser>       $projectUsers
 * @property-read Collection<int, ProjectInvitation> $invitations
 */
class Project extends Model
{
    protected $table = 'projects';

    protected $fillable = [
        'name',
        'description',
    ];

    protected $casts = [
        'id'          => 'integer',
        'name'        => 'string',
        'description' => 'string',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'project_id');
    }

    public function projectUsers(): HasMany
    {
        return $this->hasMany(ProjectUser::class, 'project_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(ProjectInvitation::class, 'project_id');
    }

    /**
     * Apakah user ini anggota project (role apa pun)?
     */
    public function hasMember(User $user): bool
    {
        return $this->projectUsers()->where('user_id', $user->id)->exists();
    }

    /**
     * Apakah user ini anggota project dengan salah satu role yang diberikan?
     */
    public function hasRole(User $user, ProjectRole ...$roles): bool
    {
        return $this->projectUsers()
            ->where('user_id', $user->id)
            ->whereIn('role', array_map(fn(ProjectRole $role) => $role->value, $roles))
            ->exists();
    }

    /**
     * Kunci baris project ini (SELECT ... FOR UPDATE) sampai transaction selesai.
     * Hanya berguna jika dipanggil di dalam DB::transaction().
     */
    public function lockRow(): static
    {
        return static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
    }
}
