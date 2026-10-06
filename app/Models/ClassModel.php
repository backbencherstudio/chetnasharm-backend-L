<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Table('classes')]
#[Fillable([
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
])]
#[Appends(['image_url'])]
class ClassModel extends Model
{
    use HasFactory;

    /** Get the attribute casts for the model. */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'curriculum' => 'array',
        ];
    }

    /** Get the full URL for the class image. */
    protected function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('storage/'.$this->image) : null;
    }

    /** Get active batches for this class.
     * @return HasMany<Batch, $this> */
    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class, 'class_id')
            ->where('active_status', 1);
    }

    /** Get all batches for this class regardless of status.
     * @return HasMany<Batch, $this> */
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
