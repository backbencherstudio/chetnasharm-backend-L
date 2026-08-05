<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class ClassModel extends Model
{
    use HasFactory;

    protected $table = 'classes';

    protected $fillable = [
        'title',
        'description',
        'short_description',
        'who_is_for',
        'curriculum',
        'is_class_recording',
        'price',
        'duration_in_days',
        'total_classes',
        'is_active',
        'image',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'curriculum' => 'array',
    ];

    protected $appends = ['image_url'];

    /** Get the full URL for the class image. */
    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('storage/'.$this->image) : null;
    }

    /** Get active batches for this class. */
    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class, 'class_id')
            ->where('active_status', 1);
    }

    /** Get all batches for this class regardless of status. */
    public function allBatches(): HasMany
    {
        return $this->hasMany(Batch::class, 'class_id');
    }

    /** Get distinct teachers assigned to batches for this class. */
    public function teachers(): Collection
    {
        $teacherIds = $this->allBatches()
            ->whereNotNull('teacher_id')
            ->distinct()
            ->pluck('teacher_id');

        return Teacher::query()
            ->whereIn('id', $teacherIds)
            ->get();
    }
}
