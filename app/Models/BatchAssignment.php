<?php

namespace App\Models;

use Database\Factories\BatchAssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BatchAssignment extends Model
{
    /** @use HasFactory<BatchAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'teacher_id',
        'title',
        'description',
        'attachment',
        'starts_at',
        'due_at',
        'total_marks',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'due_at' => 'datetime',
            'total_marks' => 'decimal:2',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class, 'assignment_id');
    }

    public function isOpenForSubmission(): bool
    {
        if ($this->starts_at !== null && now()->lt($this->starts_at)) {
            return false;
        }

        if ($this->due_at !== null && now()->gt($this->due_at)) {
            return false;
        }

        return true;
    }

    /**
     * Assignments that have reached starts_at (or have no start).
     */
    public function scopeStarted(Builder $query): Builder
    {
        return $query->where(function (Builder $builder) {
            $builder->whereNull('starts_at')
                ->orWhere('starts_at', '<=', now());
        });
    }

    /**
     * Assignments currently inside the submission window.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->started()
            ->where(function (Builder $builder) {
                $builder->whereNull('due_at')
                    ->orWhere('due_at', '>=', now());
            });
    }
}
