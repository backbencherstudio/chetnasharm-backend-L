<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Batch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Batch
 */
class PublicBatchResource extends JsonResource
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
            'name' => $this->name,
            'total_seat' => $this->total_seat,
            'filled_seat' => $this->filled_seat,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'status' => $this->status,
            'class' => $this->whenLoaded('class', fn (): ?array => $this->class ? [
                'id' => $this->class->id,
                'title' => $this->class->title,
                'description' => $this->class->description,
                'short_description' => $this->class->short_description,
                'price' => $this->class->price,
                'duration_in_days' => $this->class->duration_in_days,
                'total_classes' => $this->class->total_classes,
                'image' => $this->class->image,
                'image_url' => $this->class->image_url,
            ] : null),
            'schedules' => BatchScheduleResource::collection($this->whenLoaded('schedules')),
        ];
    }
}
