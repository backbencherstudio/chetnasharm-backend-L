<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'batch_id',
    'class_id',
    'status',
    'enrolled_at',
    'expiry_date',
])]
class Enrollment extends Model
{
    use HasFactory;

    /** Get the attribute casts for the model. */
    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'expiry_date' => 'datetime',
        ];
    }

    /** Get the enrolled user.
     * @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Get the batch this enrollment belongs to.
     * @return BelongsTo<Batch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /** Get the class this enrollment is for.
     * @return BelongsTo<ClassModel, $this> */
    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class);
    }
}
