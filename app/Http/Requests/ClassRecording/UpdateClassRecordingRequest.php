<?php

namespace App\Http\Requests\ClassRecording;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClassRecordingRequest extends FormRequest
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
            'batch_id' => 'sometimes|exists:batches,id',
            'class_date' => 'sometimes|date',
            'recording_url' => 'sometimes|url',
        ];
    }
}
