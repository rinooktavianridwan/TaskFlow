<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskActivity;
use Illuminate\Database\Eloquent\Builder;

class TaskActivityService
{
    public function index(Task $task): Builder
    {
        return TaskActivity::query()
            ->where('task_id', $task->id)
            ->with('user')
            ->orderByDesc('id');
    }
}
