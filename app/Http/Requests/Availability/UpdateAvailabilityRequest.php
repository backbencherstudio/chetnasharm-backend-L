<?php

namespace App\Http\Requests\Availability;

use App\Http\Requests\Availability\Concerns\ValidatesTeacherId;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAvailabilityRequest extends FormRequest
{
    use ValidatesTeacherId;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge($this->teacherIdRules(), [
            'day_of_week' => 'required|integer|min:0|max:6',
            'slots' => 'required|array|min:1',
            'slots.*.start_time' => 'required|date_format:H:i',
        ]);
    }
}
