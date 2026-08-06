<?php

namespace App\Http\Requests\Vocabulary;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVocabularyRequest extends FormRequest
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
        $vocabularyId = $this->route('id');

        return [
            'word' => 'required|string|max:255|unique:vocabularies,word,'.$vocabularyId,
            'meaning' => 'required|string',
            'example' => 'nullable|string',
            'pronunciation' => 'nullable|string',
            'part_of_speech' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'status' => 'nullable|integer|in:1,0',
        ];
    }
}
