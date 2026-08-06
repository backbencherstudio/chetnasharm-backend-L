<?php

namespace App\Http\Requests\User;

use App\Http\Requests\User\Concerns\FailsWithUserValidationResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdatePasswordRequest extends FormRequest
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
        $user = Auth::guard('api')->user();

        $rules = [
            'new_password' => 'required|string|min:6|confirmed',
        ];

        if (! $user?->provider) {
            $rules['current_password'] = 'required|string';
        }

        return $rules;
    }
}
