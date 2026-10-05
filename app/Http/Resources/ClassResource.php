<?php

namespace App\Http\Resources;

use App\Models\ClassModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ClassModel
 */
class ClassResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'who_is_for' => $this->who_is_for,
            'curriculum' => $this->curriculum,
            'price' => $this->price,
            'duration_in_days' => $this->duration_in_days,
            'total_classes' => $this->total_classes,
            'image' => $this->image,
            'image_url' => $this->image_url,
            'is_class_recording' => $this->is_class_recording,
            'is_active' => $this->is_active,
            'teachers_count' => $this->when(isset($this->teachers_count), fn (): int => (int) $this->teachers_count),
            'batches_count' => $this->when(
                isset($this->batches_count),
                fn (): int => (int) $this->batches_count,
                $this->whenCounted('batches')
            ),
            'teachers' => $this->when(isset($this->teachers), fn () => $this->teachers),
            'active_batches_count' => $this->whenCounted('activeBatches'),
            'batches' => BatchResource::collection($this->whenLoaded('batches')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
