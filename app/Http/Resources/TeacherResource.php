<?php

namespace App\Http\Resources;

use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Teacher
 */
class TeacherResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'country' => $this->country,
            'timezone' => $this->timezone,
            'bio' => $this->bio,
            'about' => $this->about,
            'specializations' => $this->specializations ?? [],
            'languages_spoken' => $this->languages_spoken ?? [],
            'courses_can_teach' => $this->courses_can_teach ?? [],
            'interests' => $this->interests ?? [],
            'expertise' => $this->expertise,
            'qualification' => $this->qualification,
            'years_of_exp' => $this->years_of_exp,
            'image' => $this->image,
            'image_url' => $this->image_url,
            'intro_video' => $this->intro_video,
            'intro_video_url' => $this->intro_video_url,
            'suspend_status' => $this->suspend_status,
            'is_top' => $this->is_top,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
