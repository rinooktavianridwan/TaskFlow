<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;

trait ApiResponser
{
    protected function success(mixed $data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    /**
     * Respons 204 No Content: sukses tanpa body (untuk DELETE).
     */
    protected function noContent(): Response
    {
        return response()->noContent();
    }

    /**
     * @param  class-string<JsonResource>  $resource
     */
    protected function paginated(
        LengthAwarePaginator $paginator,
        string $resource,
        string $message = 'Success',
    ): JsonResponse {
        return $this->success([
            'items' => $resource::collection($paginator),
            'meta'  => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ], $message);
    }
}


