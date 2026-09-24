<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * App\Models\ProjectUser
 *
 * @property int                     $id
 * @property int                     $user_id
 * @property int                     $project_id
 * @property string                  $role
 * @property string|null             $created_at
 * @property string|null             $updated_at
 *
 * @property-read Collection|User    $user
 * @property-read Collection|Project $project
 */
class ProjectUser extends Model
{
    protected $table = 'project_user';

    protected $fillable = [
        'user_id',
        'project_id',
        'role',
    ];

    protected $casts = [
        'id'         => 'integer',
        'user_id'    => 'integer',
        'project_id' => 'integer',
        'role'       => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
