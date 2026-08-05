<?php

namespace App\Common;

use App\Models\Setting;

class IntegrationConfig
{
    /**
     * @return array{key: ?string, secret: ?string, webhook_secret: ?string}
     */
    public function stripe(): array
    {
        return $this->stored()['stripe'];
    }

    /**
     * @return array{client_id: ?string, client_secret: ?string, mode: ?string, base_url: ?string}
     */
    public function paypal(): array
    {
        return $this->stored()['paypal'];
    }

    /**
     * @return array{token: ?string, phone_number_id: ?string, url: ?string}
     */
    public function whatsapp(): array
    {
        return $this->stored()['whatsapp'];
    }

    /**
     * @return array{
     *     stripe: array{key: ?string, secret: ?string, webhook_secret: ?string},
     *     paypal: array{client_id: ?string, client_secret: ?string, mode: ?string, base_url: ?string},
     *     whatsapp: array{token: ?string, phone_number_id: ?string, url: ?string}
     * }
     */
    private function stored(): array
    {
        $setting = Setting::query()->first();

        return $setting?->resolvedIntegrations() ?? Setting::defaultIntegrations();
    }
}
