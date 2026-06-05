<?php

namespace App\Http\Controllers\Api;

use App\Helpers\EnvHelper;
use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;

class SettingController extends Controller
{
    public function show()
    {
        $setting = Setting::get()->first();

        return response()->json([
            'success' => true,
            'message' => 'Settings retrieved successfully',
            'data' => $setting
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'class_time' => 'required|integer|min:1',
            'support_number' => 'required|string|max:20',
            'support_email' => 'required|email|max:255',
            'class_notify_time' => 'required|integer|min:1'
        ]);

        $setting = Setting::first();

        if ($setting) {
            $setting->update([
                'class_time' => $request->class_time,
                'support_number' => $request->support_number,
                'support_email' => $request->support_email,
                'class_notify_time' => $request->class_notify_time
            ]);
        } else {
            $setting = Setting::create([
                'class_time' => $request->class_time,
                'support_number' => $request->support_number,
                'support_email' => $request->support_email,
                'class_notify_time' => $request->class_notify_time
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Setting updated successfully',
            'data' => $setting
        ]);
    }

    public function getClassTime(){

        $time = Setting::select('class_time')->first();

        return response()->json([
            'success' => true,
            'message' => 'Time retrieved successfully',
            'class_time' => $time->class_time
        ]);
    }

    public function logs(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = NotificationLog::with([
            'user:id,name,email',
            'batch:id,name'
        ]);

        // Search
        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                ->orWhereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                });
            });
        }

        // Filters
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
                'per_page'     => $logs->perPage(),
                'total'        => $logs->total(),
                'last_page'    => $logs->lastPage(),
            ]
        ]);
    }

    public function support()
    {
        $setting = Setting::select('support_number', 'support_email')->first();

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Settings not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Support information retrieved successfully',
            'data' => [
                'support_number' => $setting->support_number,
                'support_email' => $setting->support_email,
            ]
        ]);
    }

    public function getEnvSettings()
    {
        return response()->json([
            'success' => true,

            'stripe' => [
                'key' => config('services.stripe.key'),
                'secret_configured' => !empty(config('services.stripe.secret')),
                'webhook_secret_configured' => !empty(config('services.stripe.webhook_secret')),
            ],

            'paypal' => [
                'client_id' => config('services.paypal.client_id'),
                'client_secret_configured' => !empty(config('services.paypal.client_secret')),
                'mode' => config('services.paypal.mode'),
                'base_url' => config('services.paypal.base_url'),
            ],

            'whatsapp' => [
                'token_configured' => !empty(config('services.whatsapp.token')),
                'phone_number_id' => config('services.whatsapp.phone_number_id'),
                'url' => config('services.whatsapp.url'),
            ],
        ]);
    }

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

            // Stripe
            if (filled(data_get($validated, 'stripe.key'))) {
                EnvHelper::set('STRIPE_KEY', data_get($validated, 'stripe.key'));
            }

            if (filled(data_get($validated, 'stripe.secret'))) {
                EnvHelper::set('STRIPE_SECRET', data_get($validated, 'stripe.secret'));
            }

            if (filled(data_get($validated, 'stripe.webhook_secret'))) {
                EnvHelper::set('STRIPE_WEBHOOK_SECRET', data_get($validated, 'stripe.webhook_secret'));
            }

            // PayPal
            if (filled(data_get($validated, 'paypal.client_id'))) {
                EnvHelper::set('PAYPAL_CLIENT_ID', data_get($validated, 'paypal.client_id'));
            }

            if (filled(data_get($validated, 'paypal.client_secret'))) {
                EnvHelper::set('PAYPAL_CLIENT_SECRET', data_get($validated, 'paypal.client_secret'));
            }

            if (filled(data_get($validated, 'paypal.mode'))) {
                EnvHelper::set('PAYPAL_MODE', data_get($validated, 'paypal.mode'));
            }

            if (filled(data_get($validated, 'paypal.base_url'))) {
                EnvHelper::set('PAYPAL_BASE_URL', data_get($validated, 'paypal.base_url'));
            }

            // WhatsApp
            if (filled(data_get($validated, 'whatsapp.token'))) {
                EnvHelper::set('WHATSAPP_TOKEN', data_get($validated, 'whatsapp.token'));
            }

            if (filled(data_get($validated, 'whatsapp.phone_number_id'))) {
                EnvHelper::set('WHATSAPP_PHONE_NUMBER_ID', data_get($validated, 'whatsapp.phone_number_id'));
            }

            if (filled(data_get($validated, 'whatsapp.url'))) {
                EnvHelper::set('WHATSAPP_API_URL', data_get($validated, 'whatsapp.url'));
            }

            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('config:cache');

            return response()->json([
                'success' => true,
                'message' => 'Environment settings updated successfully.'
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings.',
                'error' => app()->environment('local')
                    ? $e->getMessage()
                    : null
            ], 500);
        }
    }

}
