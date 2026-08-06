<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSingleAttendanceRequest extends FormRequest
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
            'user_id' => 'required|exists:users,id',
            'class_date' => 'required|date',
            'status' => 'required|in:present,absent',
        ];
    }
}
