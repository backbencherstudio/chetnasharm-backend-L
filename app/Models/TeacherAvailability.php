<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherAvailability extends Model
{
    protected $fillable = [
        'teacher_id',
        'day_of_month',
        'start_time',
        'end_time'
    ];
}
