<?php

namespace App\Http\Resources;

use App\Models\Waitlist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Waitlist
 */
class WaitlistResource extends JsonResource
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
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'user' => $this->whenLoaded('user', fn (): array => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'batch' => $this->whenLoaded('batch', fn (): array => [
                'id' => $this->batch->id,
                'name' => $this->batch->name,
                'teacher_id' => $this->batch->teacher_id,
                'teacher' => $this->batch->teacher ? [
                    'id' => $this->batch->teacher->id,
                    'name' => $this->batch->teacher->name,
                    'image_url' => $this->batch->teacher->image_url,
                    'intro_video_url' => $this->batch->teacher->intro_video_url,
                ] : null,
            ]),
        ];
    }
}
