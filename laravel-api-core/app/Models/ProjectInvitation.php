<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * App\Models\ProjectUser
 *
 * @property int                     $id
 * @property int                     $project_id
 * @property string                  $email
 * @property string                  $role
 * @property string                  $token
 * @property string                  $status
 * @property string|null             $created_at
 * @property string|null             $updated_at
 *
 * @property-read Collection|Project $project
 */
class ProjectInvitation extends Model
{
    protected $table = 'project_invitations';

    protected $fillable = [
        'project_id',
        'email',
        'role',
        'token',
        'status',
    ];

    protected $casts = [
        'id'         => 'integer',
        'project_id' => 'integer',
        'email'      => 'string',
        'role'       => 'string',
        'token'      => 'string',
        'status'     => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
