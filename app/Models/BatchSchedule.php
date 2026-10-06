<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'batch_id',
    'teacher_id',
    'day_of_week',
    'start_time',
    'end_time',
    'reminder_sent',
    'reminder_sent_date',
])]
class BatchSchedule extends Model
{
    /** Get the batch this schedule belongs to.
     * @return BelongsTo<Batch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /** Get the teacher assigned to this schedule.
     * @return BelongsTo<Teacher, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
