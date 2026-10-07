<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\TaskChecklistItem
 *
 * @property int         $id
 * @property int         $task_id
 * @property string      $title
 * @property bool        $is_done
 * @property int         $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read Task   $task
 */
class TaskChecklistItem extends Model
{
    protected $table = 'task_checklist_items';

    protected $fillable = [
        'task_id',
        'title',
        'is_done',
        'position',
    ];

    protected $casts = [
        'id'         => 'integer',
        'task_id'    => 'integer',
        'title'      => 'string',
        'is_done'    => 'boolean',
        'position'   => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }
}
