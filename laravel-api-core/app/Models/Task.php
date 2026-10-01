<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * App\Models\Task
 *
 * @property int                            $id
 * @property int                            $project_id
 * @property int                            $assigned_to
 * @property string                         $title
 * @property string                         $description
 * @property string                         $status
 * @property string                         $due_date
 * @property string|null                    $created_at
 * @property string|null                    $updated_at
 *
 * @property-read Collection|Project        $project
 * @property-read Collection|User           $user
 * @property-read Collection|TaskActivity[] $taskActivities
 */
class Task extends Model
{
    protected $table = 'tasks';

    protected $fillable = [
        'project_id',
        'assigned_to',
        'title',
        'description',
        'status',
        'due_date',
    ];

    protected $casts = [
        'id'          => 'integer',
        'project_id'  => 'integer',
        'assigned_to' => 'integer',
        'title'       => 'string',
        'description' => 'string',
        'status'      => 'string',
        'due_date'    => 'datetime',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function taskActivities(): HasMany
    {
        return $this->hasMany(TaskActivity::class, 'task_id');
    }
}
