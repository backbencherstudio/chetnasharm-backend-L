<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'word',
    'meaning',
    'example',
    'pronunciation',
    'part_of_speech',
    'image',
    'status',
])]
#[Appends(['image_url'])]
class Vocabulary extends Model
{
    use HasFactory;

    /** Get the full URL for the vocabulary image. */
    protected function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('storage/'.$this->image) : null;
    }
}
