<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'batch_id',
        'user_id',
        'class_date',
        'status'
    ];

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
