<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'settings';

    protected $fillable = [
        'class_time',
        'support_number',
        'support_email',
        'class_notify_time',
        'social_links',
    ];

    protected $casts = [
        'social_links' => 'array',
    ];

    /**
     * @return array{youtube: ?string, tiktok: ?string, instagram: ?string, linkedin: ?string, facebook: ?string}
     */
    public static function defaultSocialLinks(): array
    {
        return [
            'youtube' => null,
            'tiktok' => null,
            'instagram' => null,
            'linkedin' => null,
            'facebook' => null,
        ];
    }

    /**
     * @return array{youtube: ?string, tiktok: ?string, instagram: ?string, linkedin: ?string, facebook: ?string}
     */
    public function resolvedSocialLinks(): array
    {
        return array_merge(self::defaultSocialLinks(), $this->social_links ?? []);
    }
}
