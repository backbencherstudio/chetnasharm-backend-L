<?php

namespace App\Http\Requests\Attendance;

use App\Http\Requests\Attendance\Concerns\FailsWithSuccessFalseMessage;
use Illuminate\Foundation\Http\FormRequest;

class GetAttendanceSheetRequest extends FormRequest
{
    use FailsWithSuccessFalseMessage;

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
            'date' => 'required',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.required' => 'Date is required',
        ];
    }
}
