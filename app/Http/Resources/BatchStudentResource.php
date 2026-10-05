<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Enrollment
 */
class BatchStudentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user?->id ?? $this->user_id,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'image' => $this->user?->image,
            'image_url' => $this->user?->image_url,
            'batch_id' => $this->batch_id,
            'batch_name' => $this->batch?->name,
            'class_title' => $this->class?->title,
            'enrollment_status' => $this->status,
            'enrolled_at' => $this->enrolled_at,
            'user' => $this->whenLoaded('user'),
        ];
    }
}
