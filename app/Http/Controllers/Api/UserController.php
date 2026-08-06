<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ProfileUpdateRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdatePasswordRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Requests\User\UpdateWhatsappRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function __construct(private UserService $users) {}

    /** Create a new admin user. */
    public function store(StoreUserRequest $request): JsonResponse
    {
        try {
            $validated = $this->users->normalizeMobile($request->validated());
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid phone number format.',
            ], 422);
        }

        try {
            $user = $this->users->create($validated, $request->file('image'));

            return response()->json([
                'status' => true,
                'message' => 'User created successfully.',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'department' => $user->department,
                    'mobile' => $user->mobile,
                    'image' => $user->image,
                    'image_url' => $user->image_url,
                    'role' => $user->getRoleNames()->first(),
                ],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'User creation failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /** Get a user for editing. */
    public function edit(int $id): JsonResponse
    {
        $user = $this->users->findForEdit($id);

        return response()->json([
            'status' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'mobile' => $user->mobile,
                    'department' => $user->department,
                    'image' => $user->image,
                    'image_url' => $user->image_url,
                    'role' => $user->getRoleNames()->first(),
                ],
            ],
        ]);
    }

    /** Update the specified user. */
    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        try {
            $validated = $this->users->normalizeMobile($request->validated());
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid phone number format.',
            ], 422);
        }

        try {
            $user = $this->users->update($user, $validated, $request->file('image'));

            return response()->json([
                'status' => true,
                'message' => 'User updated successfully.',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'department' => $user->department,
                    'mobile' => $user->mobile,
                    'image' => $user->image,
                    'image_url' => $user->image_url,
                    'role' => $user->getRoleNames()->first(),
                ],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'User update failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /** Fetch the paginated user list for admin management. */
    public function data(Request $request): JsonResponse
    {
        $result = $this->users->paginateForAdmin($request);
        $users = $result['users'];

        return response()->json([
            'status' => true,
            'data' => $users->items(),

            'pagination' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],

            'counts' => $result['counts'],
        ]);
    }

    /** Toggle the suspend status of a user. */
    public function suspend(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($id == auth('api')->id()) {
            return response()->json([
                'status' => false,
                'message' => 'You cannot suspend your own account.',
            ], 400);
        }

        if ($id == 1) {
            return response()->json([
                'status' => false,
                'message' => 'You cannot suspend super admin account.',
            ], 403);
        }

        try {
            $result = $this->users->toggleSuspend($user);

            return response()->json([
                'status' => true,
                'message' => $result['message'],
                'data' => [
                    'user_id' => $result['user']->id,
                    'suspend_status' => $result['user']->suspend_status,
                ],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Operation failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /** Update the authenticated user password. */
    public function updatePass(UpdatePasswordRequest $request): JsonResponse
    {
        $user = Auth::guard('api')->user();

        $error = $this->users->updatePassword(
            $user,
            $request->input('new_password'),
            $request->input('current_password'),
        );

        if ($error) {
            return response()->json([
                'status' => false,
                'message' => $error,
            ], 422);
        }

        return response()->json([
            'status' => true,
            'message' => 'Password updated successfully.',
        ], 200);
    }

    /** Update the authenticated user profile. */
    public function profileUpdate(ProfileUpdateRequest $request): JsonResponse
    {
        $user = Auth::guard('api')->user();

        try {
            $validated = $this->users->normalizeMobile($request->validated());
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid phone number format.',
            ], 422);
        }

        try {
            $user = $this->users->updateProfile($user, $validated, $request->image('image'));

            return response()->json([
                'status' => true,
                'message' => 'Profile updated successfully.',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'mobile' => $user->mobile,
                    'department' => $user->department,
                    'image' => $user->image,
                    'image_url' => $user->image_url,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid phone number format.',
            ], 422);
        }
    }

    /** Update a user WhatsApp mobile number. */
    public function updateWhatsapp(UpdateWhatsappRequest $request): JsonResponse
    {
        try {
            $mobile = $this->users->updateWhatsappMobile(
                (int) $request->input('user_id'),
                $request->input('mobile'),
            );

            return response()->json([
                'success' => true,
                'message' => 'WhatsApp number updated successfully',
                'mobile' => $mobile,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid phone number format',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /** Delete the specified user. */
    public function destroy(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($id == auth('api')->id()) {
            return response()->json([
                'status' => false,
                'message' => 'You cannot delete your own account.',
            ], 400);
        }

        if ($user->teacher) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete user with associated teacher profile',
            ], 400);
        }

        if ($user->enrollments()->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete user with associated enrollments',
            ], 400);
        }

        try {
            $this->users->delete($user);

            return response()->json([
                'status' => true,
                'message' => 'User deleted successfully.',
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'User deletion failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
