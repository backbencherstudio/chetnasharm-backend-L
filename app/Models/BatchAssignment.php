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

    /** Get the attribute casts for the model. */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'due_at' => 'datetime',
            'total_marks' => 'decimal:2',
        ];
    }

    /** Get the batch this assignment belongs to. */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /** Get the teacher who created this assignment. */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /** Get all submissions for this assignment. */
    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class, 'assignment_id');
    }

    /** Determine whether the assignment is open for submission. */
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

    /** Scope to assignments that have reached their start time. */
    public function scopeStarted(Builder $query): Builder
    {
        return $query->where(function (Builder $builder) {
            $builder->whereNull('starts_at')
                ->orWhere('starts_at', '<=', now());
        });
    }

    /** Scope to assignments currently within the submission window. */
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
