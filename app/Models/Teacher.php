<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Teacher extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'bio',
        'password',
        'expertise',
        'zoom_email',
        'zoom_account_id',
        'is_active',
    ];



    // public function batches()
    // {
    //     return $this->hasMany(Batch::class);
    // }

    // public function schedules()
    // {
    //     return $this->hasMany(ClassSchedule::class);
    // }

    // public function students()
    // {
    //     return $this->hasManyThrough(Student::class, Batch::class);
    // }
}
