<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'project_id'  => $this->project_id,
            'title'       => $this->title,
            'description' => $this->description,
            'status'      => $this->status,
            'assigned_to' => $this->assigned_to,
            'assignee'    => $this->whenLoaded('assignee', fn() => $this->assignee
                ? [
                    'id'    => $this->assignee->id,
                    'name'  => $this->assignee->name,
                    'email' => $this->assignee->email,
                ]
                : null),
            'due_date'    => $this->due_date,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
