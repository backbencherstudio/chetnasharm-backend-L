<?php

namespace App\Http\Requests\User;

use App\Http\Requests\User\Concerns\FailsWithUserValidationResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ProfileUpdateRequest extends FormRequest
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

        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'mobile' => 'nullable|string',
            'department' => 'nullable|string|max:100',
            'image' => $this->hasFile('image')
                ? ['nullable', 'image', 'max:5120']
                : ['nullable'],
        ];
    }
}
