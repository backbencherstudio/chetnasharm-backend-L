<?php

namespace App\Http\Resources;

use App\Models\Batch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Batch
 */
class BatchResource extends JsonResource
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
            'class_id' => $this->class_id,
            'teacher_id' => $this->teacher_id,
            'total_seat' => $this->total_seat,
            'filled_seat' => $this->filled_seat,
            'start_date' => $this->start_date?->toDateString() ?? $this->start_date,
            'end_date' => $this->end_date?->toDateString() ?? $this->end_date,
            'status' => $this->status,
            'active_status' => $this->active_status,
            'zoom_link' => $this->zoom_link,
            'class' => $this->whenLoaded('class', fn () => [
                'id' => $this->class->id,
                'title' => $this->class->title,
                'description' => $this->class->description,
                'image' => $this->class->image,
                'image_url' => $this->class->image_url,
                'price' => $this->class->price,
            ]),
            'teacher' => $this->whenLoaded('teacher', fn () => [
                'id' => $this->teacher->id,
                'name' => $this->teacher->user?->name,
                'user_id' => $this->teacher->user_id,
                'country' => $this->teacher->country,
                'timezone' => $this->teacher->timezone,
                'expertise' => $this->teacher->expertise,
                'user' => $this->teacher->user ? [
                    'id' => $this->teacher->user->id,
                    'name' => $this->teacher->user->name,
                    'image' => $this->teacher->user->image,
                    'image_url' => $this->teacher->user->image_url,
                    'suspend_status' => $this->teacher->user->suspend_status,
                ] : null,
            ]),
            'schedules' => $this->whenLoaded('schedules', fn () => $this->schedules->map(fn ($s) => [
                'id' => $s->id,
                'batch_id' => $s->batch_id,
                'day_of_week' => $s->day_of_week,
                'start_time' => $s->start_time,
                'end_time' => $s->end_time,
            ])),
            'active_assignments_count' => $this->when(
                isset($this->active_assignments_count),
                fn () => (int) $this->active_assignments_count
            ),
            'assignments_count' => $this->whenCounted('assignments'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
