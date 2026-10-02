<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\TaskActivity
 *
 * @property int            $id
 * @property int            $task_id
 * @property int|null       $user_id
 * @property string         $action
 * @property string         $description
 * @property Carbon|null    $created_at
 * @property Carbon|null    $updated_at
 *
 * @property-read Task      $task
 * @property-read User|null $user
 */
class TaskActivity extends Model
{
    protected $table = 'task_activities';

    protected $fillable = [
        'task_id',
        'user_id',
        'action',
        'description',
    ];

    protected $casts = [
        'id'          => 'integer',
        'task_id'     => 'integer',
        'user_id'     => 'integer',
        'action'      => 'string',
        'description' => 'string',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
