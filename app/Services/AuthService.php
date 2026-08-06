<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Notifications\PasswordOtpNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class AuthService
{
    /**
     * @param  array{email: string, password: string}  $credentials
     * @return array{type: 'success', token: string, user: User}|array{type: 'invalid_credentials'}|array{type: 'suspended'}
     */
    public function login(array $credentials): array
    {
        if (! $token = auth('api')->attempt($credentials)) {
            return ['type' => 'invalid_credentials'];
        }

        $user = auth('api')->user();

        if ($user->suspend_status == 1) {
            auth('api')->logout();

            return ['type' => 'suspended'];
        }

        $user->role = $user->getRoleNames()->first();
        unset($user->roles);

        return [
            'type' => 'success',
            'token' => $token,
            'user' => $user,
        ];
    }

    /**
     * @return array{type: 'success', user: array<string, mixed>}|array{type: 'suspended'}
     */
    public function me(User $user): array
    {
        $user->load(['roles', 'teacher:id,user_id']);

        if ($user->suspend_status == 1) {
            auth('api')->logout();

            return ['type' => 'suspended'];
        }

        return [
            'type' => 'success',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile ?? null,
                'department' => $user->department ?? null,
                'image' => $user->image,
                'image_url' => $user->image_url,
                'role' => $user->roles->pluck('name')->implode(', '),
                'teacher_id' => $user->teacher ? $user->teacher->id : null,
                'has_password' => $user->password ? true : false,
            ],
        ];
    }

    /**
     * @return array{type: 'success', token: string, user: User}|array{type: 'suspended'}|array{type: 'token_expired'}|array{type: 'token_invalid'}
     */
    public function refresh(): array
    {
        try {
            $token = auth('api')->refresh();

            $user = auth('api')->user();

            if ($user && $user->suspend_status == 1) {
                auth('api')->logout();

                return ['type' => 'suspended'];
            }

            return [
                'type' => 'success',
                'token' => $token,
                'user' => $user,
            ];
        } catch (TokenExpiredException $e) {
            return ['type' => 'token_expired'];
        } catch (JWTException $e) {
            return ['type' => 'token_invalid'];
        }
    }

    /**
     * @param  array{name: string, email: string, password: string}  $validated
     */
    public function register(array $validated): User
    {
        return DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => bcrypt($validated['password']),
                'department' => 'Student',
                'suspend_status' => 0,
            ]);

            if (Role::where('name', 'student')->exists()) {
                $user->assignRole('student');
            }

            return $user;
        });
    }

    public function sendOtp(string $email): void
    {
        $user = User::where('email', $email)->firstOrFail();

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
    }

    /**
     * @return array{type: 'success'}|array{type: 'invalid_otp'}|array{type: 'expired_otp'}
     */
    public function verifyOtp(string $email, string $otp): array
    {
        $user = User::where('email', $email)->firstOrFail();

        $otpRecord = DB::table('password_otps')
            ->where('user_id', $user->id)
            ->first();

        if (! $otpRecord || $otpRecord->otp != $otp) {
            return ['type' => 'invalid_otp'];
        }

        if (Carbon::now()->gt(Carbon::parse($otpRecord->expires_at))) {
            return ['type' => 'expired_otp'];
        }

        return ['type' => 'success'];
    }

    /**
     * @return array{type: 'success'}|array{type: 'invalid_or_expired_otp'}
     */
    public function resetPassword(string $email, string $otp, string $newPassword): array
    {
        $user = User::where('email', $email)->firstOrFail();

        $otpRecord = DB::table('password_otps')
            ->where('user_id', $user->id)
            ->first();

        if (! $otpRecord || $otpRecord->otp != $otp || Carbon::now()->gt(Carbon::parse($otpRecord->expires_at))) {
            return ['type' => 'invalid_or_expired_otp'];
        }

        $user->password = Hash::make($newPassword);
        $user->save();

        DB::table('password_otps')->where('user_id', $user->id)->delete();

        return ['type' => 'success'];
    }

    /**
     * @return array{type: 'redirect', url: string}
     */
    public function googleRedirect(): array
    {
        $redirect = Socialite::driver('google')->stateless()
            ->with(['prompt' => 'select_account'])
            ->redirect();

        return [
            'type' => 'redirect',
            'url' => $redirect->getTargetUrl(),
        ];
    }

    /**
     * @return array{type: 'redirect', url: string}
     */
    public function googleCallback(): array
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            $user = User::where('email', $googleUser->email)->first();

            if (! $user) {
                $user = User::create([
                    'name' => $googleUser->name,
                    'email' => $googleUser->email,
                    'password' => null,
                    'department' => 'Student',
                    'image' => $googleUser->avatar,
                    'provider' => 'google',
                    'provider_id' => $googleUser->id,
                    'suspend_status' => 0,
                ]);

                if (Role::where('name', 'student')->exists()) {
                    $user->assignRole('student');
                }
            }

            if ($user->suspend_status == 1) {
                return [
                    'type' => 'redirect',
                    'url' => config('app.frontend_url').'/login?error=account_suspended',
                ];
            }

            $token = auth('api')->login($user);

            return [
                'type' => 'redirect',
                'url' => config('app.frontend_url')."/auth/callback?token={$token}",
            ];
        } catch (\Throwable $e) {
            Log::error('Google login failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'type' => 'redirect',
                'url' => config('app.frontend_url').'/login?error=google_login_failed',
            ];
        }
    }

    public function getTokenTtlSeconds(): int
    {
        return auth('api')->factory()->getTTL() * 60;
    }
}
