<?php

namespace App\Http\Requests\SpeakingTopic;

use Illuminate\Foundation\Http\FormRequest;

class StoreSpeakingTopicRequest extends FormRequest
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
            'topic' => 'required|string',
            'level' => 'nullable|string|max:50',
        ];
    }
}
