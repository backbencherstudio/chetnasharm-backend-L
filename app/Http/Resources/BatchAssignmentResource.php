<?php

namespace App\Http\Resources;

use App\Models\BatchAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BatchAssignment
 */
class BatchAssignmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'batch_id' => $this->batch_id,
            'teacher_id' => $this->teacher_id,
            'title' => $this->title,
            'description' => $this->description,
            'attachment' => $this->attachment,
            'attachment_url' => $this->attachment ? asset('storage/'.$this->attachment) : null,
            'starts_at' => $this->starts_at?->toISOString(),
            'due_at' => $this->due_at?->toISOString(),
            'total_marks' => $this->total_marks,
            'batch' => $this->whenLoaded('batch', fn () => [
                'id' => $this->batch->id,
                'name' => $this->batch->name,
            ]),
            'teacher' => $this->whenLoaded('teacher', fn () => [
                'id' => $this->teacher->id,
                'name' => $this->teacher->name,
            ]),
            'submissions_count' => $this->whenCounted('submissions'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
