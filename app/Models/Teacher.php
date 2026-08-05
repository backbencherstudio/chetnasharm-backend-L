<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    protected $fillable = [
        'name',
        'email',
        'mobile',
        'country',
        'timezone',
        'bio',
        'about',
        'specializations',
        'languages_spoken',
        'courses_can_teach',
        'interests',
        'expertise',
        'qualification',
        'years_of_exp',
        'image',
        'intro_video',
        'suspend_status',
        'is_top',
        'user_id',
    ];

    protected $casts = [
        'specializations' => 'array',
        'languages_spoken' => 'array',
        'courses_can_teach' => 'array',
        'interests' => 'array',
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
