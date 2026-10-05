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
    public function __construct(private readonly SettingService $settings) {}

    /** Retrieve application settings. */
    public function show(): JsonResponse
    {
        return $this->success(
            $this->settings->show(),
            'Settings retrieved successfully'
        );
    }

    /** Update application settings. */
    public function update(UpdateSettingRequest $request): JsonResponse
    {
        $setting = $this->settings->update($request->validated());

        return $this->success(
            $setting,
            'Setting updated successfully'
        );
    }

    /** Retrieve the configured class duration in minutes. */
    public function getClassTime(): JsonResponse
    {
        $classTime = $this->settings->getClassTime();

        if ($classTime === null) {
            return $this->error('Class time not set in settings', 422);
        }

        return $this->success(
            data: ['class_time' => $classTime],
            message: 'Time retrieved successfully',
            extra: ['class_time' => $classTime]
        );
    }

    /** List paginated notification logs. */
    public function logs(Request $request): JsonResponse
    {
        $result = $this->settings->logs($request);

        return $this->paginated(
            $result['items'],
            $result['pagination'],
            'Notification logs fetched successfully'
        );
    }

    /** Retrieve public support contact information. */
    public function support(): JsonResponse
    {
        $support = $this->settings->support();

        if ($support === null) {
            return $this->notFound('Settings not found');
        }

        return $this->success(
            $support,
            'Support information retrieved successfully'
        );
    }

    /** Get public social links. */
    public function socialLinks(): JsonResponse
    {
        return $this->success(
            $this->settings->socialLinks(),
            'Social links retrieved successfully'
        );
    }

    /** Get social links for admin. */
    public function getSocialLinks(): JsonResponse
    {
        return $this->success(
            $this->settings->socialLinks(),
            'Social links retrieved successfully'
        );
    }

    /** Update social links for fixed platform keys. */
    public function updateSocialLinks(UpdateSocialLinksRequest $request): JsonResponse
    {
        return $this->success(
            $this->settings->updateSocialLinks($request->validated()),
            'Social links updated successfully'
        );
    }

    /** Get masked integration settings for admin. */
    public function getEnvSettings(): JsonResponse
    {
        $envSettings = $this->settings->getEnvSettings();

        return $this->success(
            message: 'Success',
            extra: [
                'stripe' => $envSettings['stripe'],
                'paypal' => $envSettings['paypal'],
                'whatsapp' => $envSettings['whatsapp'],
            ]
        );
    }

    /** Update integration settings in the database. */
    public function updateEnvSettings(UpdateEnvSettingsRequest $request): JsonResponse
    {
        $this->settings->updateEnvSettings($request->validated());

        return $this->success(
            message: 'Environment settings updated successfully.'
        );
    }
}
