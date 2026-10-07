<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'task_id'     => $this->task_id,
            'action'      => $this->action,
            'description' => $this->description,
            'user'        => $this->whenLoaded('actor', fn() => [
                'id'   => $this->actor->id,
                'name' => $this->actor->name,
            ]),
            'created_at'  => $this->created_at,
        ];
    }
}
