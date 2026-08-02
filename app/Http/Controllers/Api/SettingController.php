<?php

namespace App\Http\Controllers\Api;

use App\Actions\UpdateEnvValue;
use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\Setting;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SettingController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @return void
     */
    public function __construct(private UpdateEnvValue $updateEnvValue) {}

    /**
     * Display the specified resource.
     *
     * @return JsonResponse
     */
    public function show()
    {
        $setting = Setting::query()->first();

        return response()->json([
            'success' => true,
            'message' => 'Settings retrieved successfully',
            'data' => $setting,
        ]);
    }

    /**
     * Update the specified resource.
     *
     * @return JsonResponse
     */
    public function update(Request $request)
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

    /**
     * Get the configured class duration.
     *
     * @return JsonResponse
     */
    public function getClassTime()
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

    /**
     * Fetch notification logs.
     *
     * @return JsonResponse
     */
    public function logs(Request $request)
    {
        $perPage = Pagination::perPage($request);

        $query = NotificationLog::with([
            'user:id,name,email',
            'batch:id,name',
        ]);

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
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

    /**
     * Get public support contact settings.
     *
     * @return JsonResponse
     */
    public function support()
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
     * Get masked environment settings for admin.
     *
     * @return JsonResponse
     */
    public function getEnvSettings()
    {
        return response()->json([
            'success' => true,

            'stripe' => [
                'key' => config('services.stripe.key'),
                'secret' => $this->maskSecret(config('services.stripe.secret')),
                'webhook_secret' => $this->maskSecret(config('services.stripe.webhook_secret')),
            ],

            'paypal' => [
                'client_id' => config('services.paypal.client_id'),
                'client_secret' => $this->maskSecret(config('services.paypal.client_secret')),
                'mode' => config('services.paypal.mode'),
                'base_url' => config('services.paypal.base_url'),
            ],

            'whatsapp' => [
                'token' => $this->maskSecret(config('services.whatsapp.token')),
                'phone_number_id' => config('services.whatsapp.phone_number_id'),
                'url' => config('services.whatsapp.url'),
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
     * Update environment settings from admin input.
     *
     * @return JsonResponse
     */
    public function updateEnvSettings(Request $request)
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

        try {

            if (filled(data_get($validated, 'stripe.key'))) {
                $this->updateEnvValue->handle('STRIPE_KEY', data_get($validated, 'stripe.key'));
            }

            if (filled(data_get($validated, 'stripe.secret'))) {
                $this->updateEnvValue->handle('STRIPE_SECRET', data_get($validated, 'stripe.secret'));
            }

            if (filled(data_get($validated, 'stripe.webhook_secret'))) {
                $this->updateEnvValue->handle('STRIPE_WEBHOOK_SECRET', data_get($validated, 'stripe.webhook_secret'));
            }

            if (filled(data_get($validated, 'paypal.client_id'))) {
                $this->updateEnvValue->handle('PAYPAL_CLIENT_ID', data_get($validated, 'paypal.client_id'));
            }

            if (filled(data_get($validated, 'paypal.client_secret'))) {
                $this->updateEnvValue->handle('PAYPAL_CLIENT_SECRET', data_get($validated, 'paypal.client_secret'));
            }

            if (filled(data_get($validated, 'paypal.mode'))) {
                $this->updateEnvValue->handle('PAYPAL_MODE', data_get($validated, 'paypal.mode'));
            }

            if (filled(data_get($validated, 'paypal.base_url'))) {
                $this->updateEnvValue->handle('PAYPAL_BASE_URL', data_get($validated, 'paypal.base_url'));
            }

            if (filled(data_get($validated, 'whatsapp.token'))) {
                $this->updateEnvValue->handle('WHATSAPP_TOKEN', data_get($validated, 'whatsapp.token'));
            }

            if (filled(data_get($validated, 'whatsapp.phone_number_id'))) {
                $this->updateEnvValue->handle('WHATSAPP_PHONE_NUMBER_ID', data_get($validated, 'whatsapp.phone_number_id'));
            }

            if (filled(data_get($validated, 'whatsapp.url'))) {
                $this->updateEnvValue->handle('WHATSAPP_API_URL', data_get($validated, 'whatsapp.url'));
            }

            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('config:cache');

            return response()->json([
                'success' => true,
                'message' => 'Environment settings updated successfully.',
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings.',
                'error' => app()->environment('local')
                    ? $e->getMessage()
                    : null,
            ], 500);
        }
    }
}
