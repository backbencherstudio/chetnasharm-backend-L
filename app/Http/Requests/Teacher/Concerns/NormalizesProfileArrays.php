<?php

namespace App\Http\Requests\Teacher\Concerns;

trait NormalizesProfileArrays
{
    /** Accept profile array fields as arrays or JSON strings. */
    protected function prepareForValidation(): void
    {
        foreach (['specializations', 'languages_spoken', 'courses_can_teach', 'interests'] as $field) {
            $value = $this->input($field);

            if (! is_string($value)) {
                continue;
            }

            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge([$field => $decoded]);
            }
        }
    }
}
