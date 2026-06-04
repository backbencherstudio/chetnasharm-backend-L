<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpeakingTopic extends Model
{
    protected $fillable = [
        'title',
        'level',
        'status'
    ];
}
