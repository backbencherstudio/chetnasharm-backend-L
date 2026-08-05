<?php

namespace App\Http\Controllers\Api;

use App\Common\IntegrationConfig;
use App\Common\Pagination;
use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Create a new class instance.
     */
    public function __construct(private IntegrationConfig $integrationConfig) {}

    public function show(): JsonResponse
    {
        $setting = Setting::query()->first();

        return response()->json([
            'success' => true,
            'message' => 'Settings retrieved successfully',
            'data' => $setting,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'class_time' => 'required|integer|min:1',
            'support_number' => 'required|string|max:20',
            'support_email' => 'required|email|max:255',
            'class_notify_time' => 'required|integer|min:1',
        ]);

        $setting = Setting::first();

        if ($setting) {
            $setting->update([
                'class_time' => $request->class_time,
                'support_number' => $request->support_number,
                'support_email' => $request->support_email,
                'class_notify_time' => $request->class_notify_time,
            ]);
        } else {
            $setting = Setting::create([
                'class_time' => $request->class_time,
                'support_number' => $request->support_number,
                'support_email' => $request->support_email,
                'class_notify_time' => $request->class_notify_time,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Setting updated successfully',
            'data' => $setting,
        ]);
    }

    public function getClassTime(): JsonResponse
    {
        $time = Setting::select('class_time')->first();

        if (! $time) {
            return response()->json([
                'success' => false,
                'message' => 'Class time not set in settings',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Time retrieved successfully',
            'class_time' => $time->class_time,
        ]);
    }

    public function logs(Request $request): JsonResponse
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

        $logs = $query
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Notification logs fetched successfully',
            'data' => $logs->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }

    public function support(): JsonResponse
    {
        $setting = Setting::select('support_number', 'support_email')->first();

        if (! $setting) {
            return response()->json([
                'success' => false,
                'message' => 'Settings not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Support information retrieved successfully',
            'data' => [
                'support_number' => $setting->support_number,
                'support_email' => $setting->support_email,
            ],
        ]);
    }

    /**
     * Get public social links.
     */
    public function socialLinks(): JsonResponse
    {
        $setting = Setting::query()->first();

        return response()->json([
            'success' => true,
            'message' => 'Social links retrieved successfully',
            'data' => $setting?->resolvedSocialLinks() ?? Setting::defaultSocialLinks(),
        ]);
    }

    /**
     * Get social links for admin.
     */
    public function getSocialLinks(): JsonResponse
    {
        $setting = Setting::query()->first();

        return response()->json([
            'success' => true,
            'message' => 'Social links retrieved successfully',
            'data' => $setting?->resolvedSocialLinks() ?? Setting::defaultSocialLinks(),
        ]);
    }

    /**
     * Update social links (platform keys are fixed; URLs/phone are editable).
     */
    public function updateSocialLinks(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'youtube' => ['nullable', 'url', 'max:255'],
            'tiktok' => ['nullable', 'url', 'max:255'],
            'instagram' => ['nullable', 'url', 'max:255'],
            'linkedin' => ['nullable', 'url', 'max:255'],
            'facebook' => ['nullable', 'url', 'max:255'],
        ]);

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

        return response()->json([
            'success' => true,
            'message' => 'Social links updated successfully',
            'data' => $setting->resolvedSocialLinks(),
        ]);
    }

    /**
     * Get masked integration settings for admin.
     */
    public function getEnvSettings(): JsonResponse
    {
        $stripe = $this->integrationConfig->stripe();
        $paypal = $this->integrationConfig->paypal();
        $whatsapp = $this->integrationConfig->whatsapp();

        return response()->json([
            'success' => true,

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
        ]);
    }

    /**
     * Mask a sensitive setting value.
     */
    private function maskSecret(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return '******'.substr($value, -6);
    }

    /**
     * Update integration settings in the database.
     */
    public function updateEnvSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
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
        ]);

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

        return response()->json([
            'success' => true,
            'message' => 'Environment settings updated successfully.',
        ]);
    }
}
