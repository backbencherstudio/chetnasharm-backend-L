<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ProfileUpdateRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdatePasswordRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Requests\User\UpdateWhatsappRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    /** Create a new admin user. */
    public function store(StoreUserRequest $request): JsonResponse
    {
        try {
            $validated = $this->users->normalizeMobile($request->validated());
        } catch (\Exception $e) {
            return $this->validationError(['mobile' => ['Invalid phone number format.']], 'Invalid phone number format.');
        }

        try {
            $user = $this->users->create($validated, $request->file('image'));

            return $this->success(new UserResource($user), 'User created successfully.');
        } catch (\Throwable $e) {
            return $this->error('User creation failed: '.$e->getMessage(), 500);
        }
    }

    /** Get a user for editing. */
    public function edit(int $id): JsonResponse
    {
        $user = $this->users->findForEdit($id);

        return $this->success([
            'user' => new UserResource($user),
        ]);
    }

    /** Update the specified user. */
    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        try {
            $validated = $this->users->normalizeMobile($request->validated());
        } catch (\Exception $e) {
            return $this->validationError(['mobile' => ['Invalid phone number format.']], 'Invalid phone number format.');
        }

        try {
            $user = $this->users->update($user, $validated, $request->file('image'));

            return $this->success(new UserResource($user), 'User updated successfully.');
        } catch (\Throwable $e) {
            return $this->error('User update failed: '.$e->getMessage(), 500);
        }
    }

    /** Fetch the paginated user list for admin management. */
    public function data(Request $request): JsonResponse
    {
        $result = $this->users->paginateForAdmin($request);
        $users = $result['users'];

        return $this->paginate(
            $users,
            UserResource::collection($users->items()),
            'Users retrieved successfully',
            ['counts' => $result['counts']]
        );
    }

    /** Fetch the paginated list of soft-deleted users for admin management. */
    public function trashed(Request $request): JsonResponse
    {
        $users = $this->users->paginateTrashed($request);

        return $this->paginate(
            $users,
            UserResource::collection($users->items()),
            'Trashed users retrieved successfully',
        );
    }

    /** Toggle the suspend status of a user. */
    public function suspend(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($id == auth('api')->id()) {
            return $this->error('You cannot suspend your own account.', 400);
        }

        if ($id === 1) {
            return $this->forbidden('You cannot suspend super admin account.');
        }

        try {
            $result = $this->users->toggleSuspend($user);

            return $this->success([
                'user_id' => $result['user']->id,
                'suspend_status' => $result['user']->suspend_status,
            ], $result['message']);
        } catch (\Throwable $e) {
            return $this->error('Operation failed: '.$e->getMessage(), 500);
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
            return $this->validationError(['current_password' => [$error]], $error);
        }

        return $this->success(message: 'Password updated successfully.');
    }

    /** Update the authenticated user profile. */
    public function profileUpdate(ProfileUpdateRequest $request): JsonResponse
    {
        $user = Auth::guard('api')->user();

        try {
            $validated = $this->users->normalizeMobile($request->validated());
        } catch (\Exception) {
            return $this->validationError(['mobile' => ['Invalid phone number format.']], 'Invalid phone number format.');
        }

        try {
            $user = $this->users->updateProfile($user, $validated, $request->image('image'));

            return $this->success(new UserResource($user), 'Profile updated successfully.');
        } catch (\Exception) {
            return $this->validationError(['mobile' => ['Invalid phone number format.']], 'Invalid phone number format.');
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

            return $this->success(
                message: 'WhatsApp number updated successfully',
                extra: ['mobile' => $mobile]
            );
        } catch (\Exception) {
            return $this->validationError(['mobile' => ['Invalid phone number format']], 'Invalid phone number format');
        }
    }

    /** Soft-delete the specified user. */
    public function destroy(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($id == auth('api')->id()) {
            return $this->error('You cannot delete your own account.', 400);
        }

        try {
            $this->users->delete($user);

            return $this->success(message: 'User deleted successfully.');
        } catch (\Throwable $e) {
            return $this->error('User deletion failed: '.$e->getMessage(), 500);
        }
    }

    /** Restore a soft-deleted user. */
    public function restore(int $id): JsonResponse
    {
        if ($id == auth('api')->id()) {
            return $this->error('You cannot restore your own account.', 400);
        }

        try {
            $user = $this->users->restore($id);

            return $this->success(new UserResource($user), 'User restored successfully.');
        } catch (\Throwable $e) {
            return $this->error('User restore failed: '.$e->getMessage(), 500);
        }
    }
}
