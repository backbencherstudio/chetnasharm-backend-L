<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = [
        'batch_id',
        'user_id',
        'class_date',
        'status',
    ];

    /** Get the batch this attendance record belongs to. */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /** Get the user this attendance record belongs to. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
