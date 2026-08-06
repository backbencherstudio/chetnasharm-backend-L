<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Auth\Concerns\FailsWithAuthRegisterValidationResponse;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    use FailsWithAuthRegisterValidationResponse;

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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ];
    }
}
