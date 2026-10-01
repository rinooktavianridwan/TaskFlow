<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexProjectRequest;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use App\Services\ProjectService;
use App\DTOs\CreateProjectData;
use Throwable;

class ProjectController extends Controller
{

    public function __construct(
        protected ProjectService $service,
    ) {
    }

    public function index(IndexProjectRequest $request): JsonResponse
    {
        $projects = $this->service->index($request->validated(), auth()->user())
            ->paginate($request->input('per_page', 15));

        return $this->success([
            'items' => ProjectResource::collection($projects),
            'meta'  => [
                'current_page' => $projects->currentPage(),
                'per_page'     => $projects->perPage(),
                'total'        => $projects->total(),
            ],
        ]);
    }

    public function show(Project $project): JsonResponse
    {
        return $this->success(new ProjectResource($project));
    }

    /**
     * @throws Throwable
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $this->service->create(CreateProjectData::from($request->validated()), auth()->user());

        return $this->success(new ProjectResource($project), 'Project created successfully.', 201);
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $this->service->update($project, $request->validated());

        return $this->success(message: 'Project updated successfully.', code: 204);
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->service->delete($project);

        return $this->success(message: 'Project deleted successfully.', code: 204);
    }
}
