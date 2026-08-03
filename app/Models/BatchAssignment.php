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
        'due_at',
        'total_marks',
    ];

    protected function casts(): array
    {
        return [
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
        if ($this->due_at === null) {
            return true;
        }

        return now()->lte($this->due_at);
    }

    /**
     * Assignments still open for student submission.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $builder) {
            $builder->whereNull('due_at')
                ->orWhere('due_at', '>=', now());
        });
    }
}
