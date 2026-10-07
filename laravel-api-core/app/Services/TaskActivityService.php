<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;

class TaskActivityService
{
    public function index(Task $task): Builder
    {
        return ActivityLog::query()
            ->where('task_id', $task->id)
            ->with('actor')
            ->orderByDesc('id');
    }
}
