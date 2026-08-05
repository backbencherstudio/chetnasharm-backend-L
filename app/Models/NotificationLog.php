<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    protected $fillable = [
        'user_id',
        'batch_id',
        'type',
        'message_type',
        'message',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    /** Get the user this notification was sent to. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Get the batch this notification relates to. */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
