<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'user_id',
        'batch_id',
        'enrollment_id',
        'amount',
        'currency',
        'payment_method',
        'transaction_id',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    /** Get the user who made this payment. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Get the enrollment created from this payment. */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** Get the batch this payment is for. */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
