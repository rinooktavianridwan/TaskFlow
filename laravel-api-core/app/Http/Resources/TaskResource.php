<?php

namespace App\Http\Resources;

use App\Support\TaskProgress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (! array_key_exists('checklist_items_count', $this->resource->getAttributes())) {
            $this->resource->loadChecklistCounts();
        }

        $total = (int) $this->checklist_items_count;
        $done  = (int) $this->checklist_done_count;

        return [
            'id'              => $this->id,
            'project_id'      => $this->project_id,
            'title'           => $this->title,
            'description'     => $this->description,
            'status'          => $this->status,
            'assigned_to'     => $this->assigned_to,
            'assignee'        => $this->whenLoaded('assignee', fn() => $this->assignee
                ? [
                    'id'    => $this->assignee->id,
                    'name'  => $this->assignee->name,
                    'email' => $this->assignee->email,
                ]
                : null),
            'due_date'        => $this->due_date,
            'checklist_total' => $total,
            'checklist_done'  => $done,
            'progress'        => TaskProgress::percent($this->status, $total, $done),
            'checklist'       => $this->whenLoaded(
                'checklistItems',
                fn() => TaskChecklistItemResource::collection($this->checklistItems),
            ),
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
