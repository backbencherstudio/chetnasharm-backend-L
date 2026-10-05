<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AssignmentSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AssignmentSubmission
 */
class AssignmentSubmissionResource extends JsonResource
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
            'assignment_id' => $this->assignment_id,
            'student_user_id' => $this->student_user_id,
            'student_name' => $this->relationLoaded('student') ? $this->student?->name : null,
            'student_email' => $this->relationLoaded('student') ? $this->student?->email : null,
            'file_url' => $this->file_path ? asset('storage/'.$this->file_path) : null,
            'total_marks' => $this->assignment?->total_marks,
            'obtained_marks' => $this->obtained_marks,
            'feedback' => $this->feedback,
            'graded_at' => $this->graded_at,
            'submitted_at' => $this->updated_at,
            'created_at' => $this->created_at,
        ];
    }
}
