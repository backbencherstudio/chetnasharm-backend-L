<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'batch_id',
        'class_id',
        'status',
        'enrolled_at',
        'expiry_date',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'expiry_date' => 'datetime',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function class()
    {
        return $this->belongsTo(ClassModel::class);
    }
    
}
