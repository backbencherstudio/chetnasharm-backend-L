<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'settings';

    protected $fillable = [
        'class_time',
        'support_number',
        'support_email',
        'class_notify_time',
    ];
}
