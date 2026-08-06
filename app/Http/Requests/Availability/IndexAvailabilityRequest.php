<?php

namespace App\Http\Requests\Availability;

use App\Http\Requests\Availability\Concerns\ValidatesTeacherId;
use Illuminate\Foundation\Http\FormRequest;

class IndexAvailabilityRequest extends FormRequest
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
        return $this->teacherIdRules(required: false);
    }
}
