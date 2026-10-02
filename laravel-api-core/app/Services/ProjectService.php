<?php

namespace App\Services;

use App\DTOs\CreateProjectData;
use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProjectService
{
    public function __construct()
    {
    }

    public function index(array $filter, User $user): Builder
    {
        $keyword = $filter['name'] ?? null;

        return Project::query()
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

    public function update(Project $project, array $data): Project
    {
        $project->update($data);

        return $project->fresh();
    }

    public function delete(Project $project): bool
    {
        return $project->delete();
    }

    public function withRole(Project $project, User $user): Project
    {
        return $project->load(['projectUsers' => fn ($q) => $q->where('user_id', $user->id)]);
    }
}
