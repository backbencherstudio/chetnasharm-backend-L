<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Teacher extends Model
{
    protected $fillable = [
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
        'intro_video',
        'is_top',
        'user_id',
        'zoom_email',
        'zoom_account_id',
    ];

    protected $casts = [
        'specializations' => 'array',
        'languages_spoken' => 'array',
        'courses_can_teach' => 'array',
        'interests' => 'array',
    ];

    protected $appends = [
        'name',
        'email',
        'mobile',
        'image',
        'image_url',
        'suspend_status',
        'intro_video_url',
    ];

    protected $hidden = [
        'user',
    ];

    /** Get the user account linked to this teacher. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Get the weekly availability slots for this teacher. */
    public function availabilities(): HasMany
    {
        return $this->hasMany(TeacherAvailability::class);
    }

    /** Get the batches assigned to this teacher. */
    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    /** Scope to teachers whose linked user is not suspended. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereHas('user', function (Builder $userQuery) {
            $userQuery->where('suspend_status', 0);
        });
    }

    /** Accessor for the linked user's name. */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->user?->name,
        );
    }

    /** Accessor for the linked user's email. */
    protected function email(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->user?->email,
        );
    }

    /** Accessor for the linked user's mobile number. */
    protected function mobile(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->user?->mobile,
        );
    }

    /** Accessor for the linked user's profile image path. */
    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->user?->image,
        );
    }

    /** Accessor for the linked user's suspension status. */
    protected function suspendStatus(): Attribute
    {
        return Attribute::make(
            get: fn () => (int) ($this->user?->suspend_status ?? 0),
        );
    }

    /** Accessor for the full URL of the linked user's profile image. */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                $image = $this->image;

                if (! $image) {
                    return null;
                }

                if (filter_var($image, FILTER_VALIDATE_URL)) {
                    return $image;
                }

                return asset('storage/'.$image);
            },
        );
    }

    /** Accessor for the full URL of the teacher's intro video. */
    protected function introVideoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->intro_video ? asset('storage/'.$this->intro_video) : null,
        );
    }
}
