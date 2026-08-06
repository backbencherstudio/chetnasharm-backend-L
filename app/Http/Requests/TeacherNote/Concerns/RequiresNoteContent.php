<?php

namespace App\Http\Requests\TeacherNote\Concerns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

trait RequiresNoteContent
{
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422)
        );
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (
                ! $this->filled('note') &&
                ! $this->filled('note_link') &&
                ! $this->hasFile('note_file')
            ) {
                $validator->errors()->add('note', 'Please provide note, file, or link');
            }
        });
    }
}
