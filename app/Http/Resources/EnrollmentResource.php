<?php

namespace App\Http\Resources;

use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Enrollment
 */
class EnrollmentResource extends JsonResource
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
            'user_id' => $this->user_id,
            'batch_id' => $this->batch_id,
            'class_id' => $this->class_id,
            'status' => $this->status,
            'enrolled_at' => $this->enrolled_at?->toISOString(),
            'expiry_date' => $this->expiry_date?->toISOString(),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'mobile' => $this->user->mobile,
                'image' => $this->user->image,
                'image_url' => $this->user->image_url,
            ]),
            'batch' => $this->whenLoaded('batch', fn () => [
                'id' => $this->batch->id,
                'name' => $this->batch->name,
                'teacher_id' => $this->batch->teacher_id,
            ]),
            'class' => $this->whenLoaded('class', fn () => [
                'id' => $this->class->id,
                'title' => $this->class->title,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
