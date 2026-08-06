<?php

namespace App\Services;

use App\Common\IntegrationConfig;
use App\Common\Pagination;
use App\Models\NotificationLog;
use App\Models\Setting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class SettingService
{
    public function __construct(private IntegrationConfig $integrationConfig) {}

    public function show(): ?Setting
    {
        return Setting::query()->first();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(array $validated): Setting
    {
        $setting = Setting::first();

        if ($setting) {
            $setting->update([
                'class_time' => $validated['class_time'],
                'support_number' => $validated['support_number'],
                'support_email' => $validated['support_email'],
                'class_notify_time' => $validated['class_notify_time'],
            ]);
        } else {
            $setting = Setting::create([
                'class_time' => $validated['class_time'],
                'support_number' => $validated['support_number'],
                'support_email' => $validated['support_email'],
                'class_notify_time' => $validated['class_notify_time'],
            ]);
        }

        return $setting;
    }

    public function getClassTime(): ?int
    {
        $time = Setting::select('class_time')->first();

        return $time?->class_time;
    }

    /**
     * @return array{items: array<int, NotificationLog>, pagination: array<string, int>}
     */
    public function logs(Request $request): array
    {
        $perPage = Pagination::perPage($request);

        $query = NotificationLog::with([
            'user:id,name,email',
            'batch:id,name',
        ]);

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function ($q) use ($search): void {
                $q->where('message', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search): void {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->batch_id);
        }

        if ($request->filled('message_type')) {
            $query->where('message_type', $request->message_type);
        }

        /** @var LengthAwarePaginator<int, NotificationLog> $logs */
        $logs = $query
            ->latest()
            ->paginate($perPage);

        return [
            'items' => $logs->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
        ];
    }

    /**
     * @return array{support_number: string, support_email: string}|null
     */
    public function support(): ?array
    {
        $setting = Setting::select('support_number', 'support_email')->first();

        if (! $setting) {
            return null;
        }

        return [
            'support_number' => $setting->support_number,
            'support_email' => $setting->support_email,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function socialLinks(): array
    {
        $setting = Setting::query()->first();

        return $setting?->resolvedSocialLinks() ?? Setting::defaultSocialLinks();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, string|null>
     */
    public function updateSocialLinks(array $validated): array
    {
        $socialLinks = array_merge(Setting::defaultSocialLinks(), $validated);

        $setting = Setting::query()->first();

        if ($setting) {
            $setting->update(['social_links' => $socialLinks]);
        } else {
            $setting = Setting::create([
                'class_time' => 30,
                'class_notify_time' => 30,
                'social_links' => $socialLinks,
            ]);
        }

        return $setting->resolvedSocialLinks();
    }

    /**
     * @return array<string, mixed>
     */
    public function getEnvSettings(): array
    {
        $stripe = $this->integrationConfig->stripe();
        $paypal = $this->integrationConfig->paypal();
        $whatsapp = $this->integrationConfig->whatsapp();

        return [
            'stripe' => [
                'key' => $stripe['key'],
                'secret' => $this->maskSecret($stripe['secret']),
                'webhook_secret' => $this->maskSecret($stripe['webhook_secret']),
            ],
            'paypal' => [
                'client_id' => $paypal['client_id'],
                'client_secret' => $this->maskSecret($paypal['client_secret']),
                'mode' => $paypal['mode'],
                'base_url' => $paypal['base_url'],
            ],
            'whatsapp' => [
                'token' => $this->maskSecret($whatsapp['token']),
                'phone_number_id' => $whatsapp['phone_number_id'],
                'url' => $whatsapp['url'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateEnvSettings(array $validated): void
    {
        $setting = Setting::query()->first();

        if (! $setting) {
            $setting = Setting::create([
                'class_time' => 30,
                'class_notify_time' => 30,
                'integrations' => Setting::defaultIntegrations(),
            ]);
        }

        $integrations = array_replace_recursive(
            $setting->resolvedIntegrations(),
            array_filter([
                'stripe' => array_filter([
                    'key' => data_get($validated, 'stripe.key'),
                    'secret' => data_get($validated, 'stripe.secret'),
                    'webhook_secret' => data_get($validated, 'stripe.webhook_secret'),
                ], fn ($value) => filled($value)),
                'paypal' => array_filter([
                    'client_id' => data_get($validated, 'paypal.client_id'),
                    'client_secret' => data_get($validated, 'paypal.client_secret'),
                    'mode' => data_get($validated, 'paypal.mode'),
                    'base_url' => data_get($validated, 'paypal.base_url'),
                ], fn ($value) => filled($value)),
                'whatsapp' => array_filter([
                    'token' => data_get($validated, 'whatsapp.token'),
                    'phone_number_id' => data_get($validated, 'whatsapp.phone_number_id'),
                    'url' => data_get($validated, 'whatsapp.url'),
                ], fn ($value) => filled($value)),
            ], fn ($group) => $group !== [])
        );

        $setting->update(['integrations' => $integrations]);
    }

    private function maskSecret(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return '******'.substr($value, -6);
    }
}
