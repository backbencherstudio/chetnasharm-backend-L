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
                ->orWhere('expertise', 'like', "%{$search}%")
                ->orWhere('qualification', 'like', "%{$search}%");
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
                    'bio'       => $t->bio,
                    'expertise' => $t->expertise,
                    'qualification' => $t->qualification,
                    'years_of_exp' => $t->years_of_exp,
                    'image'     => $t->image,
                    'image_url'     => $t->image ? asset('storage/' . $t->image) : null,
                    'intro_video' => $t->intro_video,
                    'intro_video_url' => $t->intro_video ? asset('storage/' . $t->intro_video) : null,
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
            'qualification' => 'nullable|string|max:500',
            'expertise'  => 'nullable|string|max:255',
            'years_of_exp' => 'nullable|integer|min:0',
            'image'      => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'intro_video' => 'nullable|file|mimes:mp4,mov,avi,webm|max:10240',
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
            if ($request->hasFile('intro_video')) {
                $validated['intro_video'] = $request->file('intro_video')->store('teacher_videos', 'public');
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

            $role = Role::where('name', 'teacher')->firstOrFail();
            $user->assignRole($role);

            $validated['user_id'] = $user->id;

            $teacher = Teacher::create($validated);

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Teacher created successfully.',
                'data'    => [
                    'id' => $teacher->id,
                    'name' => $teacher->name,
                    'email' => $teacher->email,
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
                'id'        => $teacher->id,
                'name'      => $teacher->name,
                'email'     => $teacher->email,
                'mobile'    => $teacher->mobile,
                'bio'       => $teacher->bio,
                'expertise' => $teacher->expertise,
                'years_of_exp' => $teacher->years_of_exp,
                'qualification' => $teacher->qualification,
                'intro_video' => $teacher->intro_video,
                'intro_video_url' => $teacher->intro_video_url,
                'image'     => $teacher->image,
                'image_url'     => $teacher->image_url,
                'is_active' => $teacher->is_active,
                'user_id'    => $teacher->user_id,
            ]
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $teacher = Teacher::with('user')->findOrFail($id);
        $linkedUser = $teacher->user;

        $validator = Validator::make($request->all(), [
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email,' . $teacher->user_id,
            'mobile'     => 'nullable|string|max:20',
            'bio'        => 'nullable|string',
            'expertise'  => 'nullable|string|max:255',
            'qualification' => 'nullable|string|max:500',
            'years_of_exp' => 'nullable|integer|min:0',
            'image'      => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'intro_video' => 'nullable|file|mimes:mp4,mov,avi,webm|max:10240',
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
            if ($request->hasFile('intro_video')) {
                if ($teacher->intro_video && Storage::disk('public')->exists($teacher->intro_video)) {
                    Storage::disk('public')->delete($teacher->intro_video);
                }
                $validated['intro_video'] = $request->file('intro_video')->store('teacher_videos', 'public');
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
                    'id'        => $teacher->id,
                    'name'      => $teacher->name,
                    'email'     => $teacher->email,
                    'user_id'    => $teacher->user_id,
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

            if ($teacher->intro_video && Storage::disk('public')->exists($teacher->intro_video)) {
                Storage::disk('public')->delete($teacher->intro_video);
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
