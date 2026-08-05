<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassRecording extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'class_date',
        'recording_url',
    ];

    /** Get the batch this recording belongs to. */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
