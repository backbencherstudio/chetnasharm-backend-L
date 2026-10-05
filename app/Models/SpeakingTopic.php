<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpeakingTopic extends Model
{
    protected $fillable = [
        'topic',
        'level',
        'status',
    ];
}
