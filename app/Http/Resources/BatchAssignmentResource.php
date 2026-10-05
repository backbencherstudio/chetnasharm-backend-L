<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AssignmentSubmission;
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
        $hasSubmissions = $this->relationLoaded('submissions');
        /** @var AssignmentSubmission|null $firstSubmission */
        $firstSubmission = $hasSubmissions ? $this->submissions->first() : null;

        return [
            'id' => $this->id,
            'batch_id' => $this->batch_id,
            'teacher_id' => $this->teacher_id,
            'title' => $this->title,
            'description' => $this->description,
            'attachment' => $this->attachment,
            'attachment_url' => $this->attachment ? asset('storage/'.$this->attachment) : null,
            'starts_at' => $this->starts_at,
            'due_at' => $this->due_at,
            'total_marks' => $this->total_marks,
            'submissions_count' => $this->submissions_count ?? $this->whenCounted('submissions'),
            'batch_name' => $this->relationLoaded('batch') ? $this->batch?->name : null,
            'class_title' => $this->relationLoaded('batch') && $this->batch?->relationLoaded('class')
                ? $this->batch?->class?->title
                : null,
            'is_open' => $this->isOpenForSubmission(),
            'has_submitted' => $hasSubmissions ? ($firstSubmission !== null) : $this->when(false, false),
            'my_submission' => $hasSubmissions
                ? ($firstSubmission ? (new AssignmentSubmissionResource($firstSubmission))->resolve() : null)
                : $this->when(false, null),
            'batch' => $this->whenLoaded('batch'),
            'teacher' => $this->whenLoaded('teacher'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
