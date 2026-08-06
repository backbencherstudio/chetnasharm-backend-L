<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Auth\Concerns\FailsWithForgotPasswordValidationResponse;
use Illuminate\Foundation\Http\FormRequest;

class SendOtpRequest extends FormRequest
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
        ];
    }
}
