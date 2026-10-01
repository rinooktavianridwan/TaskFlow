<?php

namespace App\Services;

use App\DTOs\CreateProjectData;
use App\Http\Requests\UpdateProjectRequest;
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
            ->whereHas('projectUsers', function (Builder $query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->when($keyword, function (Builder $query, string $value) {
                $query->where('name', 'like', "%$value%");
            });
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
                'role'    => 'owner',
            ]);

            return $project;
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
}
