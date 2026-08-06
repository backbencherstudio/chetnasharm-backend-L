<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Auth\Concerns\FailsWithAuthLoginValidationResponse;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    use FailsWithAuthLoginValidationResponse;

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
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }
}
