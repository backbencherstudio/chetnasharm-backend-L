<?php

namespace App\Http\Requests\TeacherStudent;

use Illuminate\Foundation\Http\FormRequest;

class ListStudentNotesRequest extends FormRequest
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
        ];
    }
}
