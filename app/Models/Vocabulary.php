<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Vocabulary extends Model
{
    use HasFactory;

    protected $fillable = [
        'word',
        'meaning',
        'example',
        'pronunciation',
        'part_of_speech',
        'image',
        'status'
    ];
}
