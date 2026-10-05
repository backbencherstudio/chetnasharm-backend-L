<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TeacherNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TeacherNote
 */
class TeacherNoteResource extends JsonResource
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
            'user_id' => $this->user_id,
            'batch_id' => $this->batch_id,
            'note' => $this->note,
            'note_link' => $this->note_link,
            'note_file' => $this->note_file ? asset('storage/'.$this->note_file) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'batch' => $this->whenLoaded('batch'),
        ];
    }
}
