<?php

namespace App\Http\Requests\ClassRecording;

use Illuminate\Foundation\Http\FormRequest;

class StoreClassRecordingRequest extends FormRequest
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
            'batch_id' => 'required|exists:batches,id',
            'class_date' => 'required|date',
            'recording_url' => 'required|url',
        ];
    }
}
