<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vocabulary extends Model
{
    use HasFactory;

    protected $fillable = [
        'word',
        'meaning',
        'example',
        'pronunciation',
        'part_of_speech',
        'image',
        'status',
    ];

    protected $appends = ['image_url'];

    /** Get the full URL for the vocabulary image. */
    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('storage/'.$this->image) : null;
    }
}
