<?php

namespace App\Http\Requests\Teacher;

use App\Http\Requests\Teacher\Concerns\FailsWithTeacherValidationResponse;
use App\Http\Requests\Teacher\Concerns\NormalizesProfileArrays;
use App\Models\Teacher;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTeacherRequest extends FormRequest
{
    use FailsWithTeacherValidationResponse;
    use NormalizesProfileArrays;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teacher = Teacher::with('user')->findOrFail((int) $this->route('id'));

        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$teacher->user_id,
            'mobile' => 'nullable|string',
            'country' => 'nullable|string|max:100',
            'timezone' => 'nullable|timezone',
            'bio' => 'nullable|string',
            'about' => 'nullable|string',
            'specializations' => 'nullable|array',
            'specializations.*' => 'required|string|max:255',
            'languages_spoken' => 'nullable|array',
            'languages_spoken.*' => 'required|string|max:100',
            'courses_can_teach' => 'nullable|array',
            'courses_can_teach.*' => 'required|string|max:255',
            'interests' => 'nullable|array',
            'interests.*' => 'required|string|max:255',
            'expertise' => 'nullable|string|max:255',
            'qualification' => 'nullable|string|max:500',
            'years_of_exp' => 'nullable|integer|min:0',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'intro_video' => 'nullable|file|mimes:mp4,mov,avi,webm|max:10240',
            'suspend_status' => 'nullable|in:0,1',
        ];
    }
}
