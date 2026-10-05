<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\StudentActivityNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StudentActivityNote
 */
class StudentActivityNoteResource extends JsonResource
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
            'batch_name' => $this->relationLoaded('batch') ? $this->batch?->name : null,
            'teacher_id' => $this->teacher_id,
            'teacher_name' => $this->relationLoaded('teacher')
                ? ($this->teacher?->user?->name ?? $this->teacher?->name)
                : null,
            'student_user_id' => $this->student_user_id,
            'student_name' => $this->relationLoaded('student') ? $this->student?->name : null,
            'comment' => $this->comment,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
