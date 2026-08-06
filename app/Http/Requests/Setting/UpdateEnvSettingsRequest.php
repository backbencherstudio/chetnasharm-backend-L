<?php

namespace App\Http\Requests\Setting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEnvSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stripe.key' => ['nullable', 'string'],
            'stripe.secret' => ['nullable', 'string'],
            'stripe.webhook_secret' => ['nullable', 'string'],

            'paypal.client_id' => ['nullable', 'string'],
            'paypal.client_secret' => ['nullable', 'string'],
            'paypal.mode' => ['nullable', 'in:sandbox,live'],
            'paypal.base_url' => ['nullable', 'url'],

            'whatsapp.token' => ['nullable', 'string'],
            'whatsapp.phone_number_id' => ['nullable', 'string'],
            'whatsapp.url' => ['nullable', 'url'],
        ];
    }
}
