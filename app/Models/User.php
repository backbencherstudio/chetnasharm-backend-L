<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'department',
        'mobile',
        'image',
        'suspend_status',
        'provider',
        'provider_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** Get the attribute casts for the model. */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Get the full URL for the user's profile image. */
    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }

        if (filter_var($this->image, FILTER_VALIDATE_URL)) {
            return $this->image;
        }

        return asset('storage/'.$this->image);
    }

    /** Get the identifier that will be stored in the JWT subject claim. */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /** Return a key-value array of custom claims to add to the JWT. */
    public function getJWTCustomClaims(): array
    {
        return [];
    }

    /** Get the teacher profile linked to this user. */
    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    /** Get all enrollments for this user. */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }
}
