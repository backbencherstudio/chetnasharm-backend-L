<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'teacher_id',
        'name',
        'total_seat',
        'filled_seat',
        'start_date',
        'end_date',
        'zoom_link',
        'status',
        'active_status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /** Get the class this batch belongs to. */
    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    /** Get the weekly schedules for this batch. */
    public function schedules(): HasMany
    {
        return $this->hasMany(BatchSchedule::class);
    }

    /** Get the teacher assigned to this batch. */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    /** Get all enrollments for this batch. */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** Get all assignments for this batch. */
    public function assignments(): HasMany
    {
        return $this->hasMany(BatchAssignment::class);
    }
}
