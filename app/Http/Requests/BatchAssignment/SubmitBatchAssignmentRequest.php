<?php

namespace App\Http\Requests\BatchAssignment;

use App\Http\Requests\BatchAssignment\Concerns\ValidatesAssignmentFile;
use Illuminate\Foundation\Http\FormRequest;

class SubmitBatchAssignmentRequest extends FormRequest
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
            'file' => 'required|'.$this->assignmentFileRules(),
        ];
    }
}
