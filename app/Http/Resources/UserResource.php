<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $roleName = null;
        if ($this->relationLoaded('roles')) {
            $roleName = $this->roles->pluck('name')->map(fn ($r) => ucfirst($r))->implode(', ');
        } elseif (method_exists($this->resource, 'getRoleNames')) {
            $roleName = $this->getRoleNames()->first();
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'department' => $this->department,
            'mobile' => $this->mobile,
            'image' => $this->image,
            'image_url' => $this->image_url,
            'suspend_status' => $this->suspend_status,
            'suspended' => $this->suspend_status,
            'role' => $roleName,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'teacher' => $this->whenLoaded('teacher', fn () => $this->teacher ? [
                'id' => $this->teacher->id,
                'country' => $this->teacher->country,
                'timezone' => $this->teacher->timezone,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
