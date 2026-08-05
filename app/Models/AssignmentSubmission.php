<?php

namespace App\Models;

use Database\Factories\AssignmentSubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentSubmission extends Model
{
    /** @use HasFactory<AssignmentSubmissionFactory> */
    use HasFactory;

    protected $fillable = [
        'assignment_id',
        'student_user_id',
        'file_path',
        'obtained_marks',
        'feedback',
        'graded_at',
    ];

    /** Get the attribute casts for the model. */
    protected function casts(): array
    {
        return [
            'obtained_marks' => 'decimal:2',
            'graded_at' => 'datetime',
        ];
    }

    /** Get the assignment this submission belongs to. */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(BatchAssignment::class, 'assignment_id');
    }

    /** Get the student who submitted this assignment. */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }
}
