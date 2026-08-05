<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherNote extends Model
{
    protected $fillable = [
        'title',
        'user_id',
        'batch_id',
        'note',
        'note_file',
        'note_link',
    ];

    /** Get the batch this note belongs to. */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /** Get the user who authored this note. */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
