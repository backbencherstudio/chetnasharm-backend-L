<?php

namespace App\Http\Requests\TeacherStudent;

use App\Models\StudentActivityNote;
use Illuminate\Foundation\Http\FormRequest;

class StoreStudentActivityNoteRequest extends FormRequest
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
            'batch_id' => 'required|exists:batches,id',
            'student_user_id' => 'required|exists:users,id',
            'comment' => 'required|string',
            'status' => 'required|in:'.implode(',', StudentActivityNote::STATUSES),
        ];
    }
}
