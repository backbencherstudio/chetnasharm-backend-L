<?php

namespace App\Http\Requests\BatchAssignment;

use App\Http\Requests\BatchAssignment\Concerns\ValidatesAssignmentFile;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBatchAssignmentRequest extends FormRequest
{
    use ValidatesAssignmentFile;

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
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'attachment' => 'nullable|'.$this->assignmentFileRules(),
            'starts_at' => 'nullable|date',
            'due_at' => 'nullable|date|after_or_equal:starts_at',
            'total_marks' => 'required|numeric|min:1',
        ];
    }
}
