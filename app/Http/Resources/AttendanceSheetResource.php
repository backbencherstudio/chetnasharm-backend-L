<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Enrollment
 */
class AttendanceSheetResource extends JsonResource
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
            'status' => $this->attendance_status ?? 'absent',
        ];
    }
}
