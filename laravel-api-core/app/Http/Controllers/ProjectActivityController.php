<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexProjectActivityRequest;
use App\Http\Resources\ActivityLogResource;
use App\Models\Project;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;

class ProjectActivityController extends Controller
{
    public function __construct(
        protected ActivityLogService $service,
    ) {
    }

    public function index(IndexProjectActivityRequest $request, Project $project): JsonResponse
    {
        $logs = $this->service->forProject($project, $request->validated())
            ->paginate($request->input('per_page', 15));

        return $this->paginated($logs, ActivityLogResource::class);
    }
}
