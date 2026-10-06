<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'batch_id',
    'type',
    'message_type',
    'message',
    'status',
    'sent_at',
])]
class NotificationLog extends Model
{
    /** Get the attribute casts for the model. */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /** Get the user this notification was sent to.
     * @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Get the batch this notification relates to.
     * @return BelongsTo<Batch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
