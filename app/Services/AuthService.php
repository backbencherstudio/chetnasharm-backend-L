<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Notifications\PasswordOtpNotification;
use Illuminate\Support\Facades\Date;
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
                'has_password' => (bool) $user->password,
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
        } catch (TokenExpiredException) {
            return ['type' => 'token_expired'];
        } catch (JWTException) {
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

        $otp = random_int(1000, 9999);

        DB::table('password_otps')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'otp' => $otp,
                'expires_at' => Date::now()->addMinutes(3),
                'updated_at' => Date::now(),
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

        if (Date::now()->gt(Date::parse($otpRecord->expires_at))) {
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

        if (! $otpRecord || $otpRecord->otp != $otp || Date::now()->gt(Date::parse($otpRecord->expires_at))) {
            return ['type' => 'invalid_or_expired_otp'];
        }

        $user->password = Hash::make($newPassword);
        $user->save();

        DB::table('password_otps')->where('user_id', $user->id)->delete();

        return ['type' => 'success'];
    }

    /**
     * Authenticate or register a user using a Google OAuth token.
     *
     * @return array{type: 'success', token: string, user: User}|array{type: 'suspended'}|array{type: 'failed', message: string}
     */
    public function googleLogin(string $token): array
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->userFromToken($token);

            $email = $googleUser->getEmail();
            if (! $email) {
                return [
                    'type' => 'failed',
                    'message' => 'Unable to retrieve email from Google account.',
                ];
            }

            $user = User::withTrashed()->where('email', $email)->first();

            if ($user && $user->trashed()) {
                return [
                    'type' => 'failed',
                    'message' => 'Your account has been deactivated. Please contact support.',
                ];
            }

            if (! $user) {
                $user = User::create([
                    'name' => $googleUser->getName() ?: explode('@', $email)[0],
                    'email' => $email,
                    'password' => null,
                    'department' => 'Student',
                    'image' => $googleUser->getAvatar(),
                    'provider' => 'google',
                    'provider_id' => $googleUser->getId(),
                    'suspend_status' => 0,
                ]);

                if (Role::where('name', 'student')->exists()) {
                    $user->assignRole('student');
                }
            } else {
                if (! $user->provider) {
                    $user->update([
                        'provider' => 'google',
                        'provider_id' => $googleUser->getId(),
                    ]);
                }
            }

            if ($user->suspend_status == 1) {
                return ['type' => 'suspended'];
            }

            $jwtToken = auth('api')->login($user);

            return [
                'type' => 'success',
                'token' => $jwtToken,
                'user' => $user->load('roles'),
            ];
        } catch (\Throwable $e) {
            Log::error('Google login verification failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'type' => 'failed',
                'message' => 'Invalid Google token: '.$e->getMessage(),
            ];
        }
    }

    public function getTokenTtlSeconds(): int
    {
        return auth('api')->factory()->getTTL() * 60;
    }
}
