<?php

namespace App\Services;

use App\DTOs\CreateProjectData;
use App\Enums\ActivityAction;
use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Jobs\SyncTaskReminderJob;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProjectService
{
    public function __construct(
        protected ActivityLogger $logger,
    ) {
    }

    public function index(array $filter, User $user): Builder
    {
        $keyword = $filter['name'] ?? null;

        return Project::query()
            ->select('projects.*')
            ->addSelect(['progress' => $this->progressSubquery()->whereColumn('tasks.project_id', 'projects.id')])
            ->with(['projectUsers' => fn($q) => $q->where('user_id', $user->id)])
            ->whereHas('projectUsers', function (Builder $query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->when($keyword, function (Builder $query, string $value) {
                $query->where('name', 'like', "%$value%");
            })
            ->orderByDesc('id');
    }

    /**
     * @throws Throwable
     */
    public function create(CreateProjectData $data, User $creator): Project
    {
        return DB::transaction(function () use ($data, $creator) {
            $project = Project::create([
                'name'        => $data->name,
                'description' => $data->description,
            ]);

            $project->projectUsers()->create([
                'user_id' => $creator->id,
                'role'    => ProjectRole::Owner->value,
            ]);

            return $this->withRole($project, $creator);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws Throwable
     */
    public function update(Project $project, array $data, User $actor): Project
    {
        return DB::transaction(function () use ($project, $data, $actor) {
            $project->update($data);

            if ($project->wasChanged(['name', 'description'])) {
                $this->logger->record(
                    $project->id,
                    $actor,
                    ActivityAction::ProjectUpdated,
                    'Project details updated.',
                    null,
                    [
                        'fields'       => array_keys(Arr::only($project->getChanges(), ['name', 'description'])),
                        'project_name' => $project->name,
                    ],
                );
            }

            return $project->fresh();
        });
    }

    /**
     * @throws Throwable
     */
    public function delete(Project $project): bool
    {
        return DB::transaction(function () use ($project) {
            // Task ikut terhapus lewat cascade database, jadi reminder-nya harus dibatalkan manual.
            $taskIds = $project->tasks()
                ->whereNotNull('due_date')
                ->whereNotNull('assigned_to')
                ->pluck('id');

            $deleted = $project->delete();

            if ($deleted) {
                foreach ($taskIds as $taskId) {
                    SyncTaskReminderJob::dispatch($taskId)->afterCommit();
                }
            }

            return $deleted;
        });
    }

    public function withRole(Project $project, User $user): Project
    {
        $project->load(['projectUsers' => fn($q) => $q->where('user_id', $user->id)]);
        $project->setAttribute(
            'progress',
            $this->progressSubquery()->where('tasks.project_id', $project->id)->value('progress'),
        );

        return $project;
    }

    /**
     * Progress project = rata-rata progress semua task-nya (tiap task berbobot sama).
     * Task `done` = 100, task dengan checklist = selesai/total, selain itu 0.
     */
    private function progressSubquery(): Builder
    {
        $checklist = TaskChecklistItem::query()
            ->selectRaw('task_id, COUNT(*) AS total_count, SUM(CASE WHEN is_done = 1 THEN 1 ELSE 0 END) AS done_count')
            ->groupBy('task_id');

        return Task::query()
            ->leftJoinSub($checklist, 'c', 'c.task_id', '=', 'tasks.id')
            ->selectRaw(
                'COALESCE(AVG(CASE WHEN tasks.status = ? THEN 100 WHEN c.total_count > 0 THEN c.done_count * 100.0 / c.total_count ELSE 0 END), 0) AS progress',
                [TaskStatus::Done->value],
            );
    }
}
