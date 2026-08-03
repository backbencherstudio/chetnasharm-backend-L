<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    protected $fillable = [
        'name',
        'email',
        'mobile',
        'bio',
        'expertise',
        'qualification',
        'years_of_exp',
        'image',
        'intro_video',
        'suspend_status',
        'is_top',
        'user_id',
    ];

    protected $appends = ['image_url', 'intro_video_url'];

    public function getImageUrlAttribute()
    {
        return $this->image ? asset('storage/'.$this->image) : null;
    }

    public function getIntroVideoUrlAttribute()
    {
        return $this->intro_video ? asset('storage/'.$this->intro_video) : null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function availabilities()
    {
        return $this->hasMany(TeacherAvailability::class);
    }

    public function batches()
    {
        return $this->hasMany(Batch::class);
    }
}
