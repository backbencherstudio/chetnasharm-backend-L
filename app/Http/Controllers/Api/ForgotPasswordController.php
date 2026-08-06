<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

class ForgotPasswordController extends Controller
{
    public function __construct(private AuthService $auth) {}

    /** Send a password reset OTP to the user's email. */
    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        $this->auth->sendOtp($request->validated('email'));

        return response()->json([
            'status' => true,
            'message' => 'OTP sent to your email',
        ]);
    }

    /** Verify a password reset OTP. */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->auth->verifyOtp($validated['email'], $validated['otp']);

        if ($result['type'] === 'invalid_otp') {
            return response()->json([
                'status' => false,
                'message' => 'Invalid OTP',
            ], 400);
        }

        if ($result['type'] === 'expired_otp') {
            return response()->json([
                'status' => false,
                'message' => 'OTP expired',
            ], 400);
        }

        return response()->json([
            'status' => true,
            'message' => 'OTP verified successfully',
        ]);
    }

    /** Reset the user password using a valid OTP. */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->auth->resetPassword(
            $validated['email'],
            $validated['otp'],
            $validated['new_password']
        );

        if ($result['type'] === 'invalid_or_expired_otp') {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or expired OTP',
            ], 400);
        }

        return response()->json([
            'status' => true,
            'message' => 'Password reset successfully',
        ]);
    }
}
