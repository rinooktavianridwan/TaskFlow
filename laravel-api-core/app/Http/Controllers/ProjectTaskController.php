<?php

namespace App\Http\Controllers;

use App\DTOs\CreateTaskData;
use App\Http\Requests\IndexProjectTaskRequest;
use App\Http\Requests\StoreProjectTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Throwable;

class ProjectTaskController extends Controller
{
    public function __construct(
        protected TaskService $service,
    ) {
    }

    public function index(IndexProjectTaskRequest $request, Project $project): JsonResponse
    {
        $tasks = $this->service->index($project, $request->validated(), auth()->user())
            ->paginate($request->input('per_page', 15));

        return $this->paginated($tasks, TaskResource::class);
    }

    /**
     * @throws Throwable
     */
    public function store(StoreProjectTaskRequest $request, Project $project): JsonResponse
    {
        $data = $request->validated();

        $task = $this->service->create(
            $project,
            new CreateTaskData(
                title: $data['title'],
                description: $data['description'] ?? null,
                assignedTo: $data['assigned_to'] ?? null,
                dueDate: $data['due_date'] ?? null,
            ),
            auth()->user(),
        );

        return $this->success(new TaskResource($task), 'Task created successfully.', 201);
    }
}
