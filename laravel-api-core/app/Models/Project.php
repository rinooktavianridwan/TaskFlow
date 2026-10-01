<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * App\Models\Project
 *
 * @property int                           $id
 * @property string                        $name
 * @property string                        $description
 * @property string|null                   $created_at
 * @property string|null                   $updated_at
 *
 * @property-read Collection|Task[]        $tasks
 * @property-read Collection|ProjectUser[] $projectUsers
 */
class Project extends Model
{
    protected $table = 'projects';

    protected $fillable = [
        'name',
        'description',
    ];

    protected $casts = [
        'id' => 'integer',
        'name' => 'string',
        'description' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'project_id');
    }

    public function projectUsers(): HasMany
    {
        return $this->hasMany(ProjectUser::class, 'project_id');
    }
}
