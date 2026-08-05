<?php

namespace App\Models;

use Database\Factories\StudentActivityNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentActivityNote extends Model
{
    /** @use HasFactory<StudentActivityNoteFactory> */
    use HasFactory;

    public const STATUSES = [
        'good',
        'average',
        'needs_attention',
        'bad',
    ];

    protected $fillable = [
        'teacher_id',
        'batch_id',
        'student_user_id',
        'comment',
        'status',
    ];

    /** Get the teacher who wrote this note. */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /** Get the batch this note relates to. */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /** Get the student this note is about. */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }
}
