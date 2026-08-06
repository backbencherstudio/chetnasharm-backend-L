<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Auth\Concerns\FailsWithForgotPasswordValidationResponse;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    use FailsWithForgotPasswordValidationResponse;

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
            'email' => ['required', 'email', 'exists:users,email'],
            'otp' => ['required', 'digits:4'],
            'new_password' => ['required', 'string', 'min:6', 'confirmed'],
        ];
    }
}
