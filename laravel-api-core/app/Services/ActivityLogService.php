<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogService
{
    /**
     * @param  array<string, mixed>  $filter
     */
    public function forProject(Project $project, array $filter): Builder
    {
        return ActivityLog::query()
            ->where('project_id', $project->id)
            ->with('actor')
            ->when($filter['action'] ?? null, function (Builder $query, string $action) {
                $query->where('action', $action);
            })
            ->when($filter['actor_id'] ?? null, function (Builder $query, int|string $actorId) {
                $query->where('actor_id', $actorId);
            })
            ->when($filter['task_id'] ?? null, function (Builder $query, int|string $taskId) {
                $query->where('task_id', $taskId);
            })
            ->orderByDesc('id');
    }
}
