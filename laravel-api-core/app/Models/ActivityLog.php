<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\ActivityLog
 *
 * @property int                       $id
 * @property int                       $project_id
 * @property int|null                  $actor_id
 * @property int|null                  $task_id
 * @property string                    $action
 * @property string                    $description
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null               $created_at
 *
 * @property-read Project              $project
 * @property-read User|null            $actor
 * @property-read Task|null            $task
 */
class ActivityLog extends Model
{
    /** Log bersifat append-only: tidak ada kolom updated_at. */
    public const UPDATED_AT = null;

    protected $table = 'activity_logs';

    protected $fillable = [
        'project_id',
        'actor_id',
        'task_id',
        'action',
        'description',
        'metadata',
    ];

    protected $casts = [
        'id'         => 'integer',
        'project_id' => 'integer',
        'actor_id'   => 'integer',
        'task_id'    => 'integer',
        'action'     => 'string',
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }
}
