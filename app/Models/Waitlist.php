<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Waitlist extends Model
{
    protected $fillable = [
        'user_id',
        'batch_id',
    ];

    /** Get the user on the waitlist. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Get the batch the user is waitlisted for. */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
