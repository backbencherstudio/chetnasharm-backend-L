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

    protected function casts(): array
    {
        return [
            'obtained_marks' => 'decimal:2',
            'graded_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(BatchAssignment::class, 'assignment_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }
}
