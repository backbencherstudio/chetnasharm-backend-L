<?php

namespace App\Http\Resources;

use App\Models\ClassRecording;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ClassRecording
 */
class ClassRecordingResource extends JsonResource
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
            'class_date' => $this->class_date,
            'recording_url' => $this->recording_url,
            'batch' => $this->whenLoaded('batch', fn (): array => [
                'id' => $this->batch->id,
                'name' => $this->batch->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
