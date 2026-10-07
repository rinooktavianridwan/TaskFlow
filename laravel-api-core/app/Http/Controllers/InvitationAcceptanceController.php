<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexReceivedInvitationRequest;
use App\Http\Resources\InvitationPreviewResource;
use App\Http\Resources\ReceivedInvitationResource;
use App\Models\ProjectInvitation;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Throwable;

class InvitationAcceptanceController extends Controller
{
    public function __construct(
        protected InvitationService $service,
    ) {
    }

    /**
     * Daftar undangan milik user yang login.
     */
    public function index(IndexReceivedInvitationRequest $request): JsonResponse
    {
        $invitations = $this->service->receivedBy($request->user())
            ->paginate($request->input('per_page', 15));

        return $this->paginated($invitations, ReceivedInvitationResource::class);
    }

    /**
     * Pratinjau publik. Token acak 64 karakter bertindak sebagai rahasia.
     */
    public function show(ProjectInvitation $invitation): JsonResponse
    {
        $invitation->loadMissing('project');

        return $this->success(new InvitationPreviewResource($invitation));
    }

    /**
     * @throws Throwable
     */
    public function accept(ProjectInvitation $invitation): Response
    {
        $this->service->accept($invitation, auth()->user());

        return $this->noContent();
    }

    /**
     * @throws Throwable
     */
    public function decline(ProjectInvitation $invitation): Response
    {
        $this->service->decline($invitation, auth()->user());

        return $this->noContent();
    }
}
