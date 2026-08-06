<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdateEnvSettingsRequest;
use App\Http\Requests\Setting\UpdateSettingRequest;
use App\Http\Requests\Setting\UpdateSocialLinksRequest;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct(private SettingService $settings) {}

    /** Retrieve application settings. */
    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Settings retrieved successfully',
            'data' => $this->settings->show(),
        ]);
    }

    /** Update application settings. */
    public function update(UpdateSettingRequest $request): JsonResponse
    {
        $setting = $this->settings->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Setting updated successfully',
            'data' => $setting,
        ]);
    }

    /** Retrieve the configured class duration in minutes. */
    public function getClassTime(): JsonResponse
    {
        $classTime = $this->settings->getClassTime();

        if ($classTime === null) {
            return response()->json([
                'success' => false,
                'message' => 'Class time not set in settings',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Time retrieved successfully',
            'class_time' => $classTime,
        ]);
    }

    /** List paginated notification logs. */
    public function logs(Request $request): JsonResponse
    {
        $result = $this->settings->logs($request);

        return response()->json([
            'success' => true,
            'message' => 'Notification logs fetched successfully',
            'data' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }

    /** Retrieve public support contact information. */
    public function support(): JsonResponse
    {
        $support = $this->settings->support();

        if ($support === null) {
            return response()->json([
                'success' => false,
                'message' => 'Settings not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Support information retrieved successfully',
            'data' => $support,
        ]);
    }

    /** Get public social links. */
    public function socialLinks(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Social links retrieved successfully',
            'data' => $this->settings->socialLinks(),
        ]);
    }

    /** Get social links for admin. */
    public function getSocialLinks(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Social links retrieved successfully',
            'data' => $this->settings->socialLinks(),
        ]);
    }

    /** Update social links for fixed platform keys. */
    public function updateSocialLinks(UpdateSocialLinksRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Social links updated successfully',
            'data' => $this->settings->updateSocialLinks($request->validated()),
        ]);
    }

    /** Get masked integration settings for admin. */
    public function getEnvSettings(): JsonResponse
    {
        $envSettings = $this->settings->getEnvSettings();

        return response()->json([
            'success' => true,

            'stripe' => $envSettings['stripe'],

            'paypal' => $envSettings['paypal'],

            'whatsapp' => $envSettings['whatsapp'],
        ]);
    }

    /** Update integration settings in the database. */
    public function updateEnvSettings(UpdateEnvSettingsRequest $request): JsonResponse
    {
        $this->settings->updateEnvSettings($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Environment settings updated successfully.',
        ]);
    }
}
