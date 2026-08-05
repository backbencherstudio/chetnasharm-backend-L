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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(TeacherAvailability::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    /**
     * Teachers whose linked user is not suspended.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereHas('user', function (Builder $userQuery) {
            $userQuery->where('suspend_status', 0);
        });
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->user?->name,
        );
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->user?->email,
        );
    }

    protected function mobile(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->user?->mobile,
        );
    }

    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->user?->image,
        );
    }

    protected function suspendStatus(): Attribute
    {
        return Attribute::make(
            get: fn () => (int) ($this->user?->suspend_status ?? 0),
        );
    }

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

    protected function introVideoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->intro_video ? asset('storage/'.$this->intro_video) : null,
        );
    }
}
