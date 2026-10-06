<?php

namespace App\Models;

use Database\Factories\StudentActivityNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'teacher_id',
    'batch_id',
    'student_user_id',
    'comment',
    'status',
])]
class StudentActivityNote extends Model
{
    /** @use HasFactory<StudentActivityNoteFactory> */
    use HasFactory;

    public const STATUSES = [
        'excellent',
        'good',
        'average',
        'needs_improvement',
        'requires_support',
    ];

    /** Get the teacher who wrote this note.
     * @return BelongsTo<Teacher, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /** Get the batch this note relates to.
     * @return BelongsTo<Batch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /** Get the student this note is about.
     * @return BelongsTo<User, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }
}
