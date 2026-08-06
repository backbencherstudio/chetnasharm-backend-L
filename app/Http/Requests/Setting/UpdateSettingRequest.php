<?php

namespace App\Http\Requests\Setting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
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
            'class_time' => 'required|integer|min:1',
            'support_number' => 'required|string|max:20',
            'support_email' => 'required|email|max:255',
            'class_notify_time' => 'required|integer|min:1',
        ];
    }
}
