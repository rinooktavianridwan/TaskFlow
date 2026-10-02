<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * App\Models\Task
 *
 * @property int                                $id
 * @property int                                $project_id
 * @property int|null                           $assigned_to
 * @property string                             $title
 * @property string|null                        $description
 * @property string                             $status
 * @property Carbon|null                        $due_date
 * @property Carbon|null                        $created_at
 * @property Carbon|null                        $updated_at
 *
 * @property-read Project                       $project
 * @property-read User|null                     $assignee
 * @property-read Collection<int, TaskActivity> $taskActivities
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
