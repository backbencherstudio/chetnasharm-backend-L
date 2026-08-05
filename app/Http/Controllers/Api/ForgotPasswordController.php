<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\PasswordOtpNotification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ForgotPasswordController extends Controller
{
    /** Send a password reset OTP to the user's email. */
    public function sendOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        $otp = rand(1000, 9999);

        DB::table('password_otps')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'otp' => $otp,
                'expires_at' => Carbon::now()->addMinutes(3),
                'updated_at' => Carbon::now(),
            ]
        );

        $user->notify(new PasswordOtpNotification($otp));

        return response()->json([
            'status' => true,
            'message' => 'OTP sent to your email',
        ]);
    }

    /** Verify a password reset OTP. */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|digits:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        $otpRecord = DB::table('password_otps')
            ->where('user_id', $user->id)
            ->first();

        if (! $otpRecord || $otpRecord->otp != $request->otp) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid OTP',
            ], 400);
        }

        if (Carbon::now()->gt(Carbon::parse($otpRecord->expires_at))) {
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
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|digits:4',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        $otpRecord = DB::table('password_otps')
            ->where('user_id', $user->id)
            ->first();

        if (! $otpRecord || $otpRecord->otp != $request->otp || Carbon::now()->gt(Carbon::parse($otpRecord->expires_at))) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or expired OTP',
            ], 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        DB::table('password_otps')->where('user_id', $user->id)->delete();

        return response()->json([
            'status' => true,
            'message' => 'Password reset successfully',
        ]);
    }
}
