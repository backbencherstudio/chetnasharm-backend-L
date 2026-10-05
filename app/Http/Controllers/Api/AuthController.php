<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuthController extends Controller
{
    public function __construct(private AuthService $auth) {}

    /** Authenticate a user and return an access token. */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login($request->validated());

        if ($result['type'] === 'invalid_credentials') {
            return $this->unauthorized('Invalid credentials');
        }

        if ($result['type'] === 'suspended') {
            return $this->forbidden('Your account has been suspended. Please contact admin.');
        }

        return $this->respondWithToken($result['token'], $result['user']);
    }

    /** Get the authenticated user profile. */
    public function me(): JsonResponse
    {
        $result = $this->auth->me(auth('api')->user());

        if ($result['type'] === 'suspended') {
            return $this->forbidden('Your account has been suspended. Please contact admin.');
        }

        return $this->success(
            data: $result['user'],
            message: 'User fetched successfully',
            extra: ['user' => $result['user']]
        );
    }

    /** Invalidate the current access token. */
    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return $this->success(message: 'Successfully logged out');
    }

    /** Refresh the authentication token. */
    public function refresh(): JsonResponse
    {
        $result = $this->auth->refresh();

        if ($result['type'] === 'suspended') {
            return $this->forbidden('Your account has been suspended. Please contact admin.');
        }

        if ($result['type'] === 'token_expired') {
            return $this->unauthorized('Refresh token expired. Please login again.');
        }

        if ($result['type'] === 'token_invalid') {
            return $this->unauthorized('Token invalid or not provided');
        }

        return $this->respondWithToken($result['token'], $result['user']);
    }

    /** Build the token response payload. */
    protected function respondWithToken(string $token, User $user): JsonResponse
    {
        $userResource = new UserResource($user);

        $payload = [
            'user' => $userResource,
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $this->auth->getTokenTtlSeconds(),
        ];

        return $this->success(
            data: $payload,
            message: 'Success',
            extra: $payload
        );
    }

    /** Register a new student user. */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $user = $this->auth->register($request->validated());

            return $this->created(
                new UserResource($user),
                'User registered successfully.'
            );
        } catch (Throwable $e) {
            Log::error('Registration failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->error('User registration failed.', 500, ['error' => $e->getMessage()]);
        }
    }

    /** Redirect the user to Google OAuth. */
    public function googleRedirect(): RedirectResponse
    {
        $result = $this->auth->googleRedirect();

        return redirect()->away($result['url']);
    }

    /** Handle the Google OAuth callback. */
    public function googleCallback(): RedirectResponse
    {
        $result = $this->auth->googleCallback();

        return redirect()->away($result['url']);
    }
}
