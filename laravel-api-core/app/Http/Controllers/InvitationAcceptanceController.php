<?php

namespace App\Http\Controllers;

use App\Models\ProjectInvitation;
use App\Services\InvitationService;
use Illuminate\Http\Response;
use Throwable;

class InvitationAcceptanceController extends Controller
{
    public function __construct(
        protected InvitationService $service,
    ) {
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
        $this->service->decline($invitation);

        return $this->noContent();
    }
}
