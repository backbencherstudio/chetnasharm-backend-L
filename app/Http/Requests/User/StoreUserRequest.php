<?php

namespace App\Http\Requests\User;

use App\Http\Requests\User\Concerns\FailsWithUserValidationResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    use FailsWithUserValidationResponse;

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
            'name' => ['required', 'string', 'max:100'],
            'mobile' => ['nullable', 'string'],
            'department' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'image' => ['nullable', 'image', 'max:2048'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
