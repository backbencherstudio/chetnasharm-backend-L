<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BasicQuestion extends Model
{
    protected $fillable = [
        'question',
        'level',
        'status',
    ];
}
