<?php

namespace App\Http\Requests\BatchAssignment\Concerns;

trait ValidatesAssignmentFile
{
    protected function assignmentFileRules(): string
    {
        return 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240';
    }
}
