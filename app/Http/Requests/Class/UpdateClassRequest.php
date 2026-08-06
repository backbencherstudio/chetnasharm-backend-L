<?php

namespace App\Http\Requests\Class;

use App\Http\Requests\Class\Concerns\NormalizesCurriculum;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClassRequest extends FormRequest
{
    use NormalizesCurriculum;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string',
            'who_is_for' => 'nullable|string',
            'curriculum' => 'nullable|array',
            'curriculum.*.title' => 'required|string|max:255',
            'curriculum.*.keypoints' => 'required|array|min:1',
            'curriculum.*.keypoints.*' => 'required|string|max:500',
            'is_class_recording' => 'nullable|in:0,1',
            'price' => 'sometimes|numeric|min:0',
            'duration_in_days' => 'sometimes|integer|min:1',
            'total_classes' => 'sometimes|integer|min:1',
            'is_active' => 'nullable|in:0,1',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ];
    }
}
