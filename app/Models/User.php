<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

#[Fillable([
    'name',
    'email',
    'password',
    'department',
    'mobile',
    'image',
    'suspend_status',
    'provider',
    'provider_id',
])]
#[Hidden([
    'password',
    'remember_token',
])]
#[Appends([
    'image_url',
])]
class User extends Authenticatable implements JWTSubject
{
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /** Get the attribute casts for the model. */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Get the full URL for the user's profile image. */
    protected function getImageUrlAttribute(): ?string
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

    /** Get the teacher profile linked to this user.
     * @return HasOne<Teacher, $this> */
    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    /** Get all enrollments for this user.
     * @return HasMany<Enrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }
}
