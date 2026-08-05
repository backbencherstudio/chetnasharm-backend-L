<?php

namespace App\Common;

use App\Models\Setting;

class IntegrationConfig
{
    /** Get stored Stripe integration credentials. */
    public function stripe(): array
    {
        return $this->stored()['stripe'];
    }

    /** Get stored PayPal integration credentials. */
    public function paypal(): array
    {
        return $this->stored()['paypal'];
    }

    /** Get stored WhatsApp integration credentials. */
    public function whatsapp(): array
    {
        return $this->stored()['whatsapp'];
    }

    /** Load integration settings from the database or defaults. */
    private function stored(): array
    {
        $setting = Setting::query()->first();

        return $setting?->resolvedIntegrations() ?? Setting::defaultIntegrations();
    }
}
