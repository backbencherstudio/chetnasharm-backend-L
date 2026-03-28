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
        'image',
        // 'zoom_email',
        // 'zoom_account_id',
        'is_active',
        'user_id',
    ];

    public function getImageUrlAttribute()
    {
        return $this->image ? asset('storage/' . $this->image) : null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // public function batches()
    // {
    //     return $this->hasMany(Batch::class);
    // }

    // public function schedules()
    // {
    //     return $this->hasMany(ClassSchedule::class);
    // }

}
