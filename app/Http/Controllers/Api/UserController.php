<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Pagination;
use App\Support\PhoneNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Store a newly created resource.
     *
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'mobile' => ['nullable', 'string'],
            'department' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'image' => ['nullable', 'image', 'max:2048'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        if (! empty($request->mobile)) {
            try {
                $validated['mobile'] = PhoneNormalizer::toE164($request->mobile);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid phone number format.',
                ], 422);
            }
        }

        DB::beginTransaction();

        try {
            $imagePath = null;

            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('users', 'public');
            }

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'mobile' => $validated['mobile'] ?? null,
                'department' => $validated['department'] ?? null,
                'image' => $imagePath,
                'password' => Hash::make($validated['password']),
            ]);

            $role = Role::where('name', 'admin')->firstOrFail();

            $user->assignRole($role);

            DB::commit();

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
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'User creation failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get data for editing the specified resource.
     *
     * @return JsonResponse
     */
    public function edit($id)
    {
        $user = User::findOrFail($id);

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

    /**
     * Update the specified resource.
     *
     * @return JsonResponse
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'mobile' => ['nullable', 'string'],
            'department' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'image' => ['nullable', 'image', 'max:2048'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        if (! empty($request->mobile)) {
            try {
                $validated['mobile'] = PhoneNormalizer::toE164($request->mobile);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid phone number format.',
                ], 422);
            }
        }

        DB::beginTransaction();

        try {

            if ($request->hasFile('image')) {

                if ($user->image && Storage::disk('public')->exists($user->image)) {
                    Storage::disk('public')->delete($user->image);
                }

                $user->image = $request->file('image')
                    ->store('users', 'public');
            }

            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $user->mobile = $validated['mobile'] ?? $user->mobile;
            $user->department = $validated['department'] ?? $user->department;

            if (! empty($validated['password'])) {
                $user->password = Hash::make($validated['password']);
            }

            $user->save();

            DB::commit();

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
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'User update failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fetch the paginated list for admin management.
     *
     * @return JsonResponse
     */
    public function data(Request $request)
    {
        $perPage = Pagination::perPage($request);
        $search = $request->query('search');
        $role = $request->query('role');

        $query = User::query();

        if ($role) {
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        } else {
            $query->whereHas('roles');
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        $query->with(['roles:id,name'])
            ->select('id', 'name', 'email', 'mobile', 'department', 'image', 'suspend_status', 'provider');

        $users = $query->paginate($perPage);

        $users->getCollection()->transform(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'department' => $user->department,
                'image' => $user->image,
                'image_url' => $user->image_url,
                'suspended' => $user->suspend_status,
                'role' => $user->roles->pluck('name')->map(fn ($r) => ucfirst($r))->implode(', '),
            ];
        });

        $roleCounts = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->selectRaw('roles.name as role_name, COUNT(DISTINCT model_has_roles.model_id) as total')
            ->groupBy('roles.name')
            ->pluck('total', 'role_name');

        $totalUsers = (int) DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->distinct()
            ->count('model_id');

        return response()->json([
            'status' => true,
            'data' => $users->items(),

            'pagination' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],

            'counts' => [
                'total_users' => $totalUsers,
                'admin' => (int) ($roleCounts['admin'] ?? 0),
                'teacher' => (int) ($roleCounts['teacher'] ?? 0),
                'student' => (int) ($roleCounts['student'] ?? 0),
            ],
        ]);
    }

    /**
     * Toggle the suspend status of the resource.
     *
     * @return JsonResponse
     */
    public function suspend($id)
    {
        $user = User::findOrFail($id);
        if (! $user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found.',
            ], 404);
        }
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

        DB::beginTransaction();

        try {

            $user->suspend_status = $user->suspend_status == 1 ? 0 : 1;
            $user->save();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => $user->suspend_status ? 'User suspended successfully.' : 'User reactivated successfully.',
                'data' => [
                    'user_id' => $user->id,
                    'suspend_status' => $user->suspend_status,
                ],
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Operation failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the authenticated user password.
     *
     * @return JsonResponse
     */
    public function updatePass(Request $request)
    {
        $user = Auth::guard('api')->user();

        $rules = [
            'new_password' => 'required|string|min:6|confirmed',
        ];

        if (! $user->provider) {
            $rules['current_password'] = 'required|string';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (! $user->provider) {
            if (! Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Current password is incorrect.',
                ], 422);
            }
        }

        $user->update([
            'password' => $request->new_password,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Password updated successfully.',
        ], 200);
    }

    /**
     * Update the authenticated user profile.
     *
     * @return JsonResponse
     */
    public function profileUpdate(Request $request)
    {
        $user = Auth::guard('api')->user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'mobile' => 'nullable|string',
            'department' => 'nullable|string|max:100',
            'image' => $request->hasFile('image')
                ? ['nullable', 'image']
                : ['nullable'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        unset($validated['image']);

        if (! empty($request->mobile)) {
            try {
                $validated['mobile'] = PhoneNormalizer::toE164($request->mobile);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid phone number format.',
                ], 422);
            }
        }

        if ($request->hasFile('image')) {
            if ($user->image && Storage::disk('public')->exists($user->image)) {
                Storage::disk('public')->delete($user->image);
            }

            $validated['image'] = $request->image('image')
                ->orient()
                ->scale(width: 1200)
                ->optimize()
                ->store(path: 'users', disk: 'public');
        }

        $user->update($validated);
        $user->refresh();

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
    }

    /**
     * Update a user WhatsApp mobile number.
     *
     * @return JsonResponse
     */
    public function updateWhatsapp(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'mobile' => 'required|string',
        ]);

        $user = User::findOrFail($request->user_id);

        try {
            $mobile = PhoneNormalizer::toE164($request->mobile);

            $user->update([
                'mobile' => $mobile,
            ]);

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

    /**
     * Remove the specified resource.
     *
     * @return JsonResponse
     */
    public function destroy($id)
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

        DB::beginTransaction();

        try {

            if (
                $user->image &&
                ! filter_var($user->image, FILTER_VALIDATE_URL) &&
                Storage::disk('public')->exists($user->image)
            ) {
                Storage::disk('public')->delete($user->image);
            }

            $user->syncRoles([]);
            $user->delete();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'User deleted successfully.',
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'User deletion failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
