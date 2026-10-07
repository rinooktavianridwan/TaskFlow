<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexInvitationRequest;
use App\Http\Requests\StoreInvitationRequest;
use App\Http\Resources\InvitationResource;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Throwable;

class InvitationController extends Controller
{
    public function __construct(
        protected InvitationService $service,
    ) {
    }

    public function index(IndexInvitationRequest $request, Project $project): JsonResponse
    {
        $invitations = $this->service->index($project, $request->validated())
            ->paginate($request->input('per_page', 15));

        return $this->paginated($invitations, InvitationResource::class);
    }

    /**
     * @throws Throwable
     */
    public function store(StoreInvitationRequest $request, Project $project): JsonResponse
    {
        $invitation = $this->service->create(
            $project,
            $request->validated('email'),
            $request->validated('role'),
            auth()->user(),
        );

        return $this->success(new InvitationResource($invitation), 'Invitation created successfully.', 201);
    }

    /**
     * @throws Throwable
     */
    public function destroy(Project $project, ProjectInvitation $invitation): Response
    {
        $this->service->delete($project, $invitation, auth()->user());

        return $this->noContent();
    }
}
