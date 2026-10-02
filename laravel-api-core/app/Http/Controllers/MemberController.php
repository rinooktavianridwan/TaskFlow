<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexMemberRequest;
use App\Http\Requests\UpdateMemberRoleRequest;
use App\Http\Resources\MemberResource;
use App\Models\Project;
use App\Models\User;
use App\Services\MemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Throwable;

class MemberController extends Controller
{

    public function __construct(
        protected MemberService $service,
    ) {
    }

    public function index(IndexMemberRequest $request, Project $project): JsonResponse
    {
        $members = $this->service->index($project, $request->validated())
            ->paginate($request->input('per_page', 15));

        return $this->paginated($members, MemberResource::class);
    }

    /**
     * @throws Throwable
     */
    public function update(UpdateMemberRoleRequest $request, Project $project, User $user): JsonResponse
    {
        $membership = $this->service->updateRole($project, $user, $request->validated('role'));

        return $this->success(new MemberResource($membership), 'Member role updated successfully.');
    }

    /**
     * @throws Throwable
     */
    public function destroy(Project $project, User $user): Response
    {
        $this->service->remove($project, $user);

        return $this->noContent();
    }
}
