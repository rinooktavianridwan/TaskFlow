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
use Illuminate\Http\Response;
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

        return $this->paginated($projects, ProjectResource::class);
    }

    public function show(Project $project): JsonResponse
    {
        return $this->success(new ProjectResource($this->service->withRole($project, auth()->user())));
    }

    /**
     * @throws Throwable
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $this->service->create(CreateProjectData::from($request->validated()), auth()->user());

        return $this->success(new ProjectResource($project), 'Project created successfully.', 201);
    }

    /**
     * @throws Throwable
     */
    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $project = $this->service->update($project, $request->validated(), auth()->user());

        return $this->success(
            new ProjectResource($this->service->withRole($project, auth()->user())),
            'Project updated successfully.',
        );
    }

    public function destroy(Project $project): Response
    {
        $this->service->delete($project);

        return $this->noContent();
    }
}
