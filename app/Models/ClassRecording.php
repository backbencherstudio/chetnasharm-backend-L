<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassRecording extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'class_date',
        'recording_url',
    ];

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }
}
