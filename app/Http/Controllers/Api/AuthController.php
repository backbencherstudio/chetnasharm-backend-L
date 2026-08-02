<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Laravel\Socialite\Facades\Socialite;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class AuthController extends Controller
{
    /**
     * Authenticate a user and return an access token.
     *
     * @return JsonResponse
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $credentials = $validator->validated();

        if (! $token = auth('api')->attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        $user = auth('api')->user();

        if ($user->suspend_status == 1) {
            auth('api')->logout();

            return response()->json([
                'success' => false,
                'message' => 'Your account has been suspended. Please contact admin.',
            ], 403);
        }

        $user->role = $user->getRoleNames()->first();
        unset($user->roles);

        return $this->respondWithToken($token, $user);
    }

    /**
     * Get the authenticated user profile.
     *
     * @return JsonResponse
     */
    public function me()
    {
        $user = auth('api')->user();

        $user->load(['roles', 'teacher:id,user_id']);

        if ($user->suspend_status == 1) {
            auth('api')->logout();

            return response()->json([
                'success' => false,
                'message' => 'Your account has been suspended. Please contact admin.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'User fetched successfully',
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
        ]);
    }

    /**
     * Invalidate the current access token.
     *
     * @return JsonResponse
     */
    public function logout()
    {
        auth('api')->logout();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out',
        ]);
    }

    /**
     * Refresh the authentication token.
     *
     * @return JsonResponse
     */
    public function refresh()
    {
        try {
            $token = auth('api')->refresh();

            $user = auth('api')->user();

            if ($user && $user->suspend_status == 1) {
                auth('api')->logout();

                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been suspended. Please contact admin.',
                ], 403);
            }

            return $this->respondWithToken($token, $user);

        } catch (TokenExpiredException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Refresh token expired. Please login again.',
            ], 401);

        } catch (JWTException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Token invalid or not provided',
            ], 401);
        }
    }

    /**
     * Build the token response payload.
     *
     * @return JsonResponse
     */
    protected function respondWithToken($token, $user)
    {
        return response()->json([
            'success' => true,
            'user' => $user,
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ]);
    }

    /**
     * Register a new student user.
     *
     * @return JsonResponse
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'department' => 'Student',
                'suspend_status' => 0,
            ]);

            if (Role::where('name', 'student')->exists()) {
                $user->assignRole('student');
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'User registered successfully.',
                'data' => $user,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

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

    /**
     * Redirect the user to Google OAuth.
     *
     * @return RedirectResponse
     */
    public function googleRedirect()
    {
        return Socialite::driver('google')->stateless()
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    /**
     * Handle the Google OAuth callback.
     *
     * @return RedirectResponse
     */
    public function googleCallback()
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
                return redirect(config('app.frontend_url').'/login?error=account_suspended');
            }
            $token = auth('api')->login($user);

            return redirect(config('app.frontend_url')."/auth/callback?token={$token}");

        } catch (\Throwable $e) {

            return redirect(config('app.frontend_url').'/login?error=google_login_failed');
        }
    }
}
