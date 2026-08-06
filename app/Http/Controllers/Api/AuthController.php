<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function __construct(private AuthService $auth) {}

    /** Authenticate a user and return an access token. */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login($request->validated());

        if ($result['type'] === 'invalid_credentials') {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        if ($result['type'] === 'suspended') {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been suspended. Please contact admin.',
            ], 403);
        }

        return $this->respondWithToken($result['token'], $result['user']);
    }

    /** Get the authenticated user profile. */
    public function me(): JsonResponse
    {
        $result = $this->auth->me(auth('api')->user());

        if ($result['type'] === 'suspended') {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been suspended. Please contact admin.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'User fetched successfully',
            'user' => $result['user'],
        ]);
    }

    /** Invalidate the current access token. */
    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out',
        ]);
    }

    /** Refresh the authentication token. */
    public function refresh(): JsonResponse
    {
        $result = $this->auth->refresh();

        if ($result['type'] === 'suspended') {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been suspended. Please contact admin.',
            ], 403);
        }

        if ($result['type'] === 'token_expired') {
            return response()->json([
                'success' => false,
                'message' => 'Refresh token expired. Please login again.',
            ], 401);
        }

        if ($result['type'] === 'token_invalid') {
            return response()->json([
                'success' => false,
                'message' => 'Token invalid or not provided',
            ], 401);
        }

        return $this->respondWithToken($result['token'], $result['user']);
    }

    /** Build the token response payload. */
    protected function respondWithToken(string $token, User $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'user' => $user,
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $this->auth->getTokenTtlSeconds(),
        ]);
    }

    /** Register a new student user. */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $user = $this->auth->register($request->validated());

            return response()->json([
                'status' => true,
                'message' => 'User registered successfully.',
                'data' => $user,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Registration failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'User registration failed.',
                'error' => $e->getMessage(),
            ], 500);
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
