<?php

namespace App\Http\Requests\TeacherNote;

use App\Http\Requests\TeacherNote\Concerns\RequiresNoteContent;
use Illuminate\Foundation\Http\FormRequest;

class StoreTeacherNoteRequest extends FormRequest
{
    use RequiresNoteContent;

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
            'title' => 'required|string',
            'batch_id' => 'required|exists:batches,id',
            'note' => 'nullable|string',
            'note_link' => 'nullable|url',
            'note_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ];
    }
}
