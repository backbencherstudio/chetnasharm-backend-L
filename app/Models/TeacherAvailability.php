<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'teacher_id',
    'day_of_week',
    'start_time',
    'end_time',
])]
class TeacherAvailability extends Model
{
    /** Get the teacher who owns this availability slot.
     * @return BelongsTo<Teacher, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
