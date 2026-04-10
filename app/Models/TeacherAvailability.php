<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherAvailability extends Model
{
    protected $fillable = [
        'teacher_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }
}
