<?php

namespace App\Http\Requests\User;

use App\Http\Requests\User\Concerns\FailsWithUserValidationResponse;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
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
        $user = User::findOrFail((int) $this->route('id'));

        return [
            'name' => ['required', 'string', 'max:100'],
            'mobile' => ['nullable', 'string'],
            'department' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'image' => ['nullable', 'image', 'max:2048'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];
    }
}
