<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'teacher_ids',
        'is_class_recording',
        'price',
        'duration_in_days',
        'total_classes',
        'is_active',
        'image',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'teacher_ids' => 'array',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        return $this->image ? asset('storage/'.$this->image) : null;
    }

    public function batches()
    {
        return $this->hasMany(Batch::class, 'class_id')
            ->where('active_status', 1);
    }

    public function teachers()
    {
        return Teacher::whereIn('id', $this->teacher_ids ?? [])->get();
    }
}
