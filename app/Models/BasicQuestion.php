<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BasicQuestion extends Model
{
    protected $fillable = [
        'question',
        'level',
        'status'
    ];
}
