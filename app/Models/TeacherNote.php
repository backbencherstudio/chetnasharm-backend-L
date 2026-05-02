<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}