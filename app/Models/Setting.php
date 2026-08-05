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
        'integrations',
    ];

    protected $casts = [
        'social_links' => 'array',
        'integrations' => 'array',
    ];

    /** Return default social link placeholders. */
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

    /** Merge stored social links with defaults. */
    public function resolvedSocialLinks(): array
    {
        return array_merge(self::defaultSocialLinks(), $this->social_links ?? []);
    }

    /** Return default third-party integration placeholders. */
    public static function defaultIntegrations(): array
    {
        return [
            'stripe' => [
                'key' => null,
                'secret' => null,
                'webhook_secret' => null,
            ],
            'paypal' => [
                'client_id' => null,
                'client_secret' => null,
                'mode' => 'sandbox',
                'base_url' => null,
            ],
            'whatsapp' => [
                'token' => null,
                'phone_number_id' => null,
                'url' => 'https://graph.facebook.com/v25.0',
            ],
        ];
    }

    /** Merge stored integrations with defaults. */
    public function resolvedIntegrations(): array
    {
        return array_replace_recursive(self::defaultIntegrations(), $this->integrations ?? []);
    }
}
