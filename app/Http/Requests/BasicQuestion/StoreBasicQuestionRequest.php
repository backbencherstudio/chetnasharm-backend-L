<?php

namespace App\Http\Requests\BasicQuestion;

use Illuminate\Foundation\Http\FormRequest;

class StoreBasicQuestionRequest extends FormRequest
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
            'question' => 'required|string',
            'level' => 'nullable|string|max:50',
        ];
    }
}
