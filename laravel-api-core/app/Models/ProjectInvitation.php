<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\ProjectInvitation
 *
 * @property int          $id
 * @property int          $project_id
 * @property string       $email
 * @property string       $role
 * @property string       $token
 * @property string       $status
 * @property Carbon|null  $expires_at
 * @property Carbon|null  $created_at
 * @property Carbon|null  $updated_at
 *
 * @property-read Project $project
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
        'expires_at',
    ];

    protected $casts = [
        'id'         => 'integer',
        'project_id' => 'integer',
        'email'      => 'string',
        'role'       => 'string',
        'token'      => 'string',
        'status'     => 'string',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }
}
