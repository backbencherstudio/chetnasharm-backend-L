<?php

namespace App\Http\Requests\BatchAssignment;

use App\Models\AssignmentSubmission;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class GradeBatchAssignmentRequest extends FormRequest
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
            'obtained_marks' => 'required|numeric|min:0',
            'feedback' => 'nullable|string',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $submission = AssignmentSubmission::query()
                ->with('assignment')
                ->where('id', $this->route('submissionId'))
                ->first();

            if (! $submission || ! $submission->assignment) {
                return;
            }

            $maxMarks = $submission->assignment->total_marks;
            $obtainedMarks = $this->input('obtained_marks');

            if ($obtainedMarks !== null && is_numeric($obtainedMarks) && (float) $obtainedMarks > (float) $maxMarks) {
                $validator->errors()->add(
                    'obtained_marks',
                    "The obtained marks field must not be greater than {$maxMarks}."
                );
            }
        });
    }
}
