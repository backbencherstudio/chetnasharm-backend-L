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
        'price',
        'duration_in_days',
        'total_classes',
        'is_active',
        'image',
    ];

    protected $casts = [
        'price' => 'decimal:2',
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

}
