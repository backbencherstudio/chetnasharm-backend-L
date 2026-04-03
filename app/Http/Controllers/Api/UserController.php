<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function store(Request $request)
    {
        $validator =Validator::make($request->all(), [
            'name'       => ['required', 'string', 'max:100'],
            'mobile'     => ['nullable', 'string', 'max:20'],
            'department' => ['nullable', 'string', 'max:100'],
            'email'      => ['required', 'email', 'max:255', 'unique:users,email'],
            'image'      => ['nullable', 'image', 'max:2048'],
            'password'   => ['required', 'confirmed', Password::defaults()],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation errors',
                'errors'  => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        DB::beginTransaction();

        try {
            $imagePath = null;

            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('users', 'public');
            }
            $user = User::create([
                'name'       => $validated['name'],
                'email'      => $validated['email'],
                'mobile'     => $validated['mobile'] ?? null,
                'department' => $validated['department'] ?? null,
                'image'      => $imagePath,
                'password'   => Hash::make($validated['password']),
            ]);

            $role = Role::where('name', 'admin')->firstOrFail();

            $user->assignRole($role);

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'User created successfully.',
                    'data'    => [
                        'id'         => $user->id,
                        'name'       => $user->name,
                        'email'      => $user->email,
                        'department' => $user->department,
                        'mobile'     => $user->mobile,
                        'image'      => $user->image,
                        'image_url'      => $user->image_url,
                        'role'       => $user->getRoleNames()->first(),
                    ]
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status'  => false,
                'message' => 'User creation failed.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);

        return response()->json([
            'status' => true,
            'data'   => [
                'user' => [
                    'id'         => $user->id,
                    'name'       => $user->name,
                    'email'      => $user->email,
                    'mobile'     => $user->mobile,
                    'department' => $user->department,
                    'image'      => $user->image,
                    'image_url'      => $user->image_url,
                    'role'       => $user->getRoleNames()->first(),
                ],
            ]
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name'       => ['required', 'string', 'max:100'],
            'mobile'     => ['nullable', 'string', 'max:20'],
            'department' => ['nullable', 'string', 'max:100'],
            'email'      => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'image'      => ['nullable', 'image', 'max:2048'],
            'password'   => ['nullable', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation errors',
                'errors'  => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        DB::beginTransaction();

        try {

            if ($request->hasFile('image')) {

                if ($user->image && Storage::disk('public')->exists($user->image)) {
                    Storage::disk('public')->delete($user->image);
                }

                $imagePath = $request->file('image')->store('users', 'public');

                $user->image = $imagePath;
            }

            $user->name       = $validated['name'];
            $user->email      = $validated['email'];
            $user->mobile     = $validated['mobile'] ?? null;
            $user->department = $validated['department'] ?? null;

            if (!empty($validated['password'])) {
                $user->password = Hash::make($validated['password']);
            }

            $user->save();

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'User updated successfully.',
                'data'    => [
                    'id'         => $user->id,
                    'name'       => $user->name,
                    'email'      => $user->email,
                    'department' => $user->department,
                    'mobile'     => $user->mobile,
                    'image'      => $user->image,
                    'image_url'      => $user->image_url,
                    'role'       => $user->getRoleNames()->first(),
                ]
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status'  => false,
                'message' => 'User update failed.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function data()
    {
        $users = User::whereHas('roles')
            ->with(['roles' => function ($query) {
                $query->select('id', 'name');
            }])
            ->select('id', 'name', 'email', 'mobile', 'department', 'image')
            ->get()
            ->map(function ($user) {
                return [
                    'id'         => $user->id,
                    'name'       => $user->name,
                    'email'      => $user->email,
                    'mobile'     => $user->mobile,
                    'department' => $user->department,
                    'image'      => $user->image,
                    'image_url'      => $user->image_url,
                    'role'       => $user->roles->map(fn($r) => ucfirst($r->name))->implode(', '),
                ];
            });

        return response()->json([
            'status' => true,
            'data'   => $users,
        ]);
    }


    public function destroy($id)
    {
        $user = User::findOrFail($id);

        DB::beginTransaction();

        try {

           if ($user->image && Storage::disk('public')->exists($user->image)) {
                Storage::disk('public')->delete($user->image);
            }

            $user->syncRoles([]);

            $user->delete();

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'User deleted successfully.'
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status'  => false,
                'message' => 'User deletion failed.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function updatePass(Request $request)
    {
        $user = Auth::guard('api')->user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation errors',
                'errors'  => $validator->errors()
            ], 422);
        }

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status'  => false,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'status'  => true,
            'message' => 'Password updated successfully.',
        ], 200);
    }

    public function profileUpdate(Request $request)
    {
        $user = Auth::guard('api')->user();

        $validator = Validator::make($request->all(), [
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email,' . $user->id,
            'mobile'     => 'nullable|string|max:20',
            'department' => 'nullable|string|max:100',
            'image'      => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation errors',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        if ($request->hasFile('image')) {

            if ($user->image && Storage::disk('public')->exists($user->image)) {
                Storage::disk('public')->delete($user->image);
            }

            $validated['image'] = $request->file('image')->store('users', 'public');
        }

        $user->update($validated);

        return response()->json([
            'status'  => true,
            'message' => 'Profile updated successfully.',
            'data'    => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'mobile'     => $user->mobile,
                'department' => $user->department,
                'image'      => $user->image,
                'image_url'      => $user->image_url,
            ],
        ], 200);
    }

}
