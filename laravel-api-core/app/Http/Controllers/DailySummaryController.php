<?php

namespace App\Http\Controllers;

use App\Http\Requests\DailySummaryRequest;
use App\Services\DailySummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class DailySummaryController extends Controller
{
    public function __construct(
        protected DailySummaryService $service,
    ) {
    }

    public function __invoke(DailySummaryRequest $request): JsonResponse
    {
        $user = auth()->user();

        $today = Carbon::now($user->timezone ?: 'UTC')->toDateString();
        $from  = $request->validated('from') ?? $today;
        $to    = $request->validated('to') ?? $from;

        return $this->success($this->service->forUser($user, $from, $to));
    }
}
