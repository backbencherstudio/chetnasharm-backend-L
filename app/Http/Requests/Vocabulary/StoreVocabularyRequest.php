<?php

namespace App\Http\Requests\Vocabulary;

use Illuminate\Foundation\Http\FormRequest;

class StoreVocabularyRequest extends FormRequest
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
            'word' => 'required|string|max:255|unique:vocabularies,word',
            'meaning' => 'required|string',
            'example' => 'nullable|string',
            'pronunciation' => 'nullable|string',
            'part_of_speech' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
        ];
    }
}
