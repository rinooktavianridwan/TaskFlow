<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Throwable;

class TaskController extends Controller
{
    public function __construct(
        protected TaskService $service,
    ) {
    }

    public function show(Task $task): JsonResponse
    {
        return $this->success(new TaskResource($this->service->show($task)));
    }

    /**
     * @throws Throwable
     */
    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $task = $this->service->update($task, auth()->user(), $request->validated());

        return $this->success(new TaskResource($task), 'Task updated successfully.');
    }

    public function destroy(Task $task): Response
    {
        $this->service->delete($task, auth()->user());

        return $this->noContent();
    }
}
