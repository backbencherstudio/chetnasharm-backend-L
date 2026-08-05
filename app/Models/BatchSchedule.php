<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchSchedule extends Model
{
    protected $fillable = [
        'batch_id',
        'teacher_id',
        'day_of_week',
        'start_time',
        'end_time',
        'reminder_sent',
        'reminder_sent_date',
    ];

    /** Get the batch this schedule belongs to. */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /** Get the teacher assigned to this schedule. */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
