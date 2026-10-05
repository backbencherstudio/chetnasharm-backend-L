<?php

namespace App\Http\Resources;

use App\Models\Vocabulary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Vocabulary
 */
class VocabularyResource extends JsonResource
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
            'word' => $this->word,
            'meaning' => $this->meaning,
            'example' => $this->example,
            'pronunciation' => $this->pronunciation,
            'part_of_speech' => $this->part_of_speech,
            'image' => $this->image,
            'image_url' => $this->image_url,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
