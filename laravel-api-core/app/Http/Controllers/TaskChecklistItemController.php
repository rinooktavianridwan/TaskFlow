<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskChecklistItemRequest;
use App\Http\Requests\UpdateTaskChecklistItemRequest;
use App\Http\Resources\TaskChecklistItemResource;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Services\TaskChecklistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Throwable;

class TaskChecklistItemController extends Controller
{
    public function __construct(
        protected TaskChecklistService $service,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function store(StoreTaskChecklistItemRequest $request, Task $task): JsonResponse
    {
        [$item, $task] = $this->service->create($task, $request->validated('title'), auth()->user());

        return $this->success([
            'item' => new TaskChecklistItemResource($item),
            'task' => new TaskResource($task),
        ], 'Checklist item created successfully.', 201);
    }

    /**
     * @throws Throwable
     */
    public function update(UpdateTaskChecklistItemRequest $request, TaskChecklistItem $item): JsonResponse
    {
        [$item, $task] = $this->service->update($item, auth()->user(), $request->validated());

        return $this->success([
            'item' => new TaskChecklistItemResource($item),
            'task' => new TaskResource($task),
        ], 'Checklist item updated successfully.');
    }

    /**
     * @throws Throwable
     */
    public function destroy(TaskChecklistItem $item): Response
    {
        $this->service->delete($item, auth()->user());

        return $this->noContent();
    }
}
