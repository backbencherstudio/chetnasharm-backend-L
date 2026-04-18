<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }
}
