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

    /**
     * @return array{
     *     stripe: array{key: ?string, secret: ?string, webhook_secret: ?string},
     *     paypal: array{client_id: ?string, client_secret: ?string, mode: ?string, base_url: ?string},
     *     whatsapp: array{token: ?string, phone_number_id: ?string, url: ?string}
     * }
     */
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

    /**
     * @return array{
     *     stripe: array{key: ?string, secret: ?string, webhook_secret: ?string},
     *     paypal: array{client_id: ?string, client_secret: ?string, mode: ?string, base_url: ?string},
     *     whatsapp: array{token: ?string, phone_number_id: ?string, url: ?string}
     * }
     */
    public function resolvedIntegrations(): array
    {
        return array_replace_recursive(self::defaultIntegrations(), $this->integrations ?? []);
    }
}
