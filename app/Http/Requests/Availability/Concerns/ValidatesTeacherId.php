<?php

namespace App\Http\Requests\Availability\Concerns;

trait ValidatesTeacherId
{
    /**
     * @return array<string, mixed>
     */
    protected function teacherIdRules(bool $required = true): array
    {
        if ($this->user('api')?->hasRole('teacher')) {
            return [];
        }

        return [
            'teacher_id' => ($required ? 'required' : 'nullable').'|integer|exists:teachers,id',
        ];
    }
}
