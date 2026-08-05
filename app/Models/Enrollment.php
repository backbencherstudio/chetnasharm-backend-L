<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'batch_id',
        'class_id',
        'status',
        'enrolled_at',
        'expiry_date',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'expiry_date' => 'datetime',
    ];

    /** Get the enrolled user. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Get the batch this enrollment belongs to. */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /** Get the class this enrollment is for. */
    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class);
    }
}
