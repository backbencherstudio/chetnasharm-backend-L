<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class TeacherController extends Controller
{
    public function data(Request $request)
    {
        $perPage = $request->query('per_page', 10);

        $query = Teacher::query();

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('mobile', 'like', "%{$search}%")
                ->orWhere('bio', 'like', "%{$search}%")
                ->orWhere('expertise', 'like', "%{$search}%");
            });
        }

        if ($request->filled('expertise')) {
            $query->where('expertise', 'like', "%{$request->expertise}%");
        }

        $teachers = $query->latest()->paginate($perPage);

        return response()->json([
            'status' => true,
            'data' => collect($teachers->items())->map(function ($t) {
                return [
                    'id'        => $t->id,
                    'name'      => $t->name,
                    'email'     => $t->email,
                    'mobile'    => $t->mobile,
                    'expertise' => $t->expertise,
                    'bio'       => $t->bio,
                    'image'     => $t->image ? asset('storage/' . $t->image) : null,
                    'is_active' => $t->is_active,
                ];
            }),
            'pagination' => [
                'current_page' => $teachers->currentPage(),
                'per_page'     => $teachers->perPage(),
                'total'        => $teachers->total(),
                'last_page'    => $teachers->lastPage(),
            ]
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:teachers,email|unique:users,email',
            'mobile'     => 'nullable|string|max:20',
            'bio'        => 'nullable|string',
            'expertise'  => 'nullable|string|max:255',
            'image'      => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            // 'zoom_email' => 'nullable|email',
            // 'zoom_account_id' => 'nullable|string',
            'is_active'  => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        DB::beginTransaction();

        try {
            if ($request->hasFile('image')) {
                $validated['image'] = $request->file('image')->store('teachers', 'public');
            }

            $randomPassword = '12345678';

            $user = User::create([
                'name'       => $validated['name'],
                'email'      => $validated['email'],
                'mobile'     => $validated['mobile'] ?? null,
                'department' => 'Teacher',
                'password'   => Hash::make($randomPassword),
                'image'      => $validated['image'] ?? null,
            ]);

            $role = Role::where('name', 'teacher')->first();
            $user->assignRole($role);

            $validated['user_id'] = $user->id;

            $teacher = Teacher::create($validated);

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Teacher created successfully.',
                'data'    => [
                    'teacher' => $teacher,
                    'user'    => [
                        'id'       => $user->id,
                        'email'    => $user->email,
                        'password' => $randomPassword,
                    ]
                ]
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status'  => false,
                'message' => 'Failed to create teacher.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function edit($id)
    {
        $teacher = Teacher::with('user')->findOrFail($id);

        return response()->json([
            'status' => true,
            'data'   => [
                'teacher' => [
                    'id'        => $teacher->id,
                    'name'      => $teacher->name,
                    'email'     => $teacher->email,
                    'mobile'    => $teacher->mobile,
                    'expertise' => $teacher->expertise,
                    'bio'       => $teacher->bio,
                    'image'     => $teacher->image_url,
                    'is_active' => $teacher->is_active,
                    'user_id'    => $teacher->user_id,
                ],
            ]
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $teacher = Teacher::with('user')->findOrFail($id);
        $linkedUser = $teacher->user;

        $validator = Validator::make($request->all(), [
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:teachers,email,' . $teacher->id . '|unique:users,email,' . $teacher->user_id,
            'mobile'     => 'nullable|string|max:20',
            'bio'        => 'nullable|string',
            'expertise'  => 'nullable|string|max:255',
            'image'      => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'is_active'  => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        DB::beginTransaction();

        try {
            if ($request->hasFile('image')) {
                if ($teacher->image && Storage::disk('public')->exists($teacher->image)) {
                    Storage::disk('public')->delete($teacher->image);
                }

                $validated['image'] = $request->file('image')->store('teachers', 'public');
            }

            $teacher->update($validated);

            if ($linkedUser) {
                $linkedUser->name = $teacher->name;
                $linkedUser->email = $teacher->email;
                $linkedUser->mobile = $teacher->mobile;
                $linkedUser->image = $teacher->image;
                $linkedUser->department = 'Teacher';
                $linkedUser->save();
            }

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Teacher updated successfully.',
                'data'    => [
                    'teacher' => [
                        'id'        => $teacher->id,
                        'name'      => $teacher->name,
                        'email'     => $teacher->email,
                        'mobile'    => $teacher->mobile,
                        'expertise' => $teacher->expertise,
                        'bio'       => $teacher->bio,
                        'image'     => $teacher->image_url,
                        'is_active' => $teacher->is_active,
                        'user_id'    => $teacher->user_id,
                    ]
                ]
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status'  => false,
                'message' => 'Failed to update teacher.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $teacher = Teacher::with('user')->findOrFail($id);
        $linkedUser = $teacher->user;

        DB::beginTransaction();

        try {
            if ($teacher->image && Storage::disk('public')->exists($teacher->image)) {
                Storage::disk('public')->delete($teacher->image);
            }

            $teacher->delete();

            if ($linkedUser) {
                $linkedUser->syncRoles([]);
                $linkedUser->delete();
            }

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Teacher deleted successfully.'
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status'  => false,
                'message' => 'Failed to delete teacher.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
