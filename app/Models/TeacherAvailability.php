<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherAvailability extends Model
{
    protected $fillable = [
        'teacher_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    /** Get the teacher who owns this availability slot. */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
