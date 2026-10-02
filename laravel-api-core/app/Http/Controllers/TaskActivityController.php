<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexTaskActivityRequest;
use App\Http\Resources\TaskActivityResource;
use App\Models\Task;
use App\Services\TaskActivityService;
use Illuminate\Http\JsonResponse;

class TaskActivityController extends Controller
{
    public function __construct(
        protected TaskActivityService $service,
    ) {
    }

    public function index(IndexTaskActivityRequest $request, Task $task): JsonResponse
    {
        $activities = $this->service->index($task)
            ->paginate($request->input('per_page', 15));

        return $this->paginated($activities, TaskActivityResource::class);
    }
}
