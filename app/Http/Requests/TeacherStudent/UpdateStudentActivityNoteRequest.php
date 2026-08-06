<?php

namespace App\Http\Requests\TeacherStudent;

use App\Models\StudentActivityNote;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentActivityNoteRequest extends FormRequest
{
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
            'comment' => 'required|string',
            'status' => 'required|in:'.implode(',', StudentActivityNote::STATUSES),
        ];
    }
}
