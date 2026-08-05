<?php

namespace App\Http\Controllers\Api;

use App\Common\Pagination;
use App\Common\PhoneNormalizer;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class TeacherController extends Controller
{
    /**
     * Fetch the paginated list for admin management.
     *
     * @return JsonResponse
     */
    public function data(Request $request)
    {
        $perPage = Pagination::perPage($request);

        $query = Teacher::query()->with('user');

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('bio', 'like', "%{$search}%")
                    ->orWhere('expertise', 'like', "%{$search}%")
                    ->orWhere('qualification', 'like', "%{$search}%")
                    ->orWhere('about', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('mobile', 'like', "%{$search}%");
                    });
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
                    'id' => $t->id,
                    'name' => $t->name,
                    'email' => $t->email,
                    'mobile' => $t->mobile,
                    'country' => $t->country,
                    'timezone' => $t->timezone,
                    'bio' => $t->bio,
                    'about' => $t->about,
                    'specializations' => $t->specializations ?? [],
                    'languages_spoken' => $t->languages_spoken ?? [],
                    'courses_can_teach' => $t->courses_can_teach ?? [],
                    'interests' => $t->interests ?? [],
                    'expertise' => $t->expertise,
                    'qualification' => $t->qualification,
                    'years_of_exp' => $t->years_of_exp,
                    'image' => $t->image,
                    'image_url' => $t->image_url,
                    'intro_video' => $t->intro_video,
                    'intro_video_url' => $t->intro_video_url,
                    'suspend_status' => $t->suspend_status,
                    'is_top' => $t->is_top,
                ];
            }),
            'pagination' => [
                'current_page' => $teachers->currentPage(),
                'per_page' => $teachers->perPage(),
                'total' => $teachers->total(),
                'last_page' => $teachers->lastPage(),
            ],
        ], 200);
    }

    /**
     * Store a newly created resource.
     *
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        $this->normalizeProfileArrayInputs($request);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'mobile' => 'nullable|string',
            'country' => 'nullable|string|max:100',
            'timezone' => 'nullable|timezone',
            'bio' => 'nullable|string',
            'about' => 'nullable|string',
            'specializations' => 'nullable|array',
            'specializations.*' => 'required|string|max:255',
            'languages_spoken' => 'nullable|array',
            'languages_spoken.*' => 'required|string|max:100',
            'courses_can_teach' => 'nullable|array',
            'courses_can_teach.*' => 'required|string|max:255',
            'interests' => 'nullable|array',
            'interests.*' => 'required|string|max:255',
            'qualification' => 'nullable|string|max:500',
            'expertise' => 'nullable|string|max:255',
            'years_of_exp' => 'nullable|integer|min:0',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'intro_video' => 'nullable|file|mimes:mp4,mov,avi,webm|max:20480',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
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
                $validated['image'] = $request->image('image')
                    ->orient()
                    ->cover(800, 800)
                    ->optimize()
                    ->store(path: 'teachers', disk: 'public');
            }

            if ($request->hasFile('intro_video')) {
                $validated['intro_video'] = $request->file('intro_video')
                    ->store('teacher_videos', 'public');
            }

            $randomPassword = '12345678';

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'mobile' => $validated['mobile'] ?? null,
                'department' => 'Teacher',
                'password' => Hash::make($randomPassword),
                'image' => $validated['image'] ?? null,
                'suspend_status' => 0,
            ]);

            $role = Role::where('name', 'teacher')->firstOrFail();
            $user->assignRole($role);

            $teacher = Teacher::create([
                ...Arr::except($validated, ['name', 'email', 'mobile', 'image']),
                'user_id' => $user->id,
            ]);

            $teacher->setRelation('user', $user);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Teacher created successfully.',
                'data' => [
                    'id' => $teacher->id,
                    'name' => $teacher->name,
                    'email' => $teacher->email,
                    'country' => $teacher->country,
                    'timezone' => $teacher->timezone,
                    'about' => $teacher->about,
                    'specializations' => $teacher->specializations ?? [],
                    'languages_spoken' => $teacher->languages_spoken ?? [],
                    'courses_can_teach' => $teacher->courses_can_teach ?? [],
                    'interests' => $teacher->interests ?? [],
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'password' => $randomPassword,
                    ],
                ],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Failed to create teacher.',
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
        $teacher = Teacher::query()->with('user')->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'email' => $teacher->email,
                'mobile' => $teacher->mobile,
                'country' => $teacher->country,
                'timezone' => $teacher->timezone,
                'bio' => $teacher->bio,
                'about' => $teacher->about,
                'specializations' => $teacher->specializations ?? [],
                'languages_spoken' => $teacher->languages_spoken ?? [],
                'courses_can_teach' => $teacher->courses_can_teach ?? [],
                'interests' => $teacher->interests ?? [],
                'expertise' => $teacher->expertise,
                'years_of_exp' => $teacher->years_of_exp,
                'qualification' => $teacher->qualification,
                'intro_video' => $teacher->intro_video,
                'intro_video_url' => $teacher->intro_video_url,
                'image' => $teacher->image,
                'image_url' => $teacher->image_url,
                'suspend_status' => $teacher->suspend_status,
                'user_id' => $teacher->user_id,
            ],
        ], 200);
    }

    /**
     * Update the specified resource.
     *
     * @return JsonResponse
     */
    public function update(Request $request, $id)
    {
        $teacher = Teacher::with('user')->findOrFail($id);
        $linkedUser = $teacher->user;

        $this->normalizeProfileArrayInputs($request);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$teacher->user_id,
            'mobile' => 'nullable|string',
            'country' => 'nullable|string|max:100',
            'timezone' => 'nullable|timezone',
            'bio' => 'nullable|string',
            'about' => 'nullable|string',
            'specializations' => 'nullable|array',
            'specializations.*' => 'required|string|max:255',
            'languages_spoken' => 'nullable|array',
            'languages_spoken.*' => 'required|string|max:100',
            'courses_can_teach' => 'nullable|array',
            'courses_can_teach.*' => 'required|string|max:255',
            'interests' => 'nullable|array',
            'interests.*' => 'required|string|max:255',
            'expertise' => 'nullable|string|max:255',
            'qualification' => 'nullable|string|max:500',
            'years_of_exp' => 'nullable|integer|min:0',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'intro_video' => 'nullable|file|mimes:mp4,mov,avi,webm|max:10240',
            'suspend_status' => 'nullable|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
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
                if ($linkedUser?->image && Storage::disk('public')->exists($linkedUser->image)) {
                    Storage::disk('public')->delete($linkedUser->image);
                }

                $validated['image'] = $request->image('image')
                    ->orient()
                    ->cover(800, 800)
                    ->optimize()
                    ->store(path: 'teachers', disk: 'public');
            }

            if ($request->hasFile('intro_video')) {

                if ($teacher->intro_video && Storage::disk('public')->exists($teacher->intro_video)) {
                    Storage::disk('public')->delete($teacher->intro_video);
                }

                $validated['intro_video'] = $request->file('intro_video')
                    ->store('teacher_videos', 'public');
            }

            $userPayload = Arr::only($validated, ['name', 'email', 'mobile', 'image', 'suspend_status']);
            $teacherPayload = Arr::except($validated, ['name', 'email', 'mobile', 'image', 'suspend_status']);

            $teacher->update($teacherPayload);

            if ($linkedUser) {
                $linkedUser->fill([
                    ...$userPayload,
                    'department' => 'Teacher',
                ]);
                $linkedUser->save();
                $teacher->setRelation('user', $linkedUser->fresh());
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Teacher updated successfully.',
                'data' => [
                    'id' => $teacher->id,
                    'name' => $teacher->name,
                    'email' => $teacher->email,
                    'country' => $teacher->country,
                    'timezone' => $teacher->timezone,
                    'about' => $teacher->about,
                    'specializations' => $teacher->specializations ?? [],
                    'languages_spoken' => $teacher->languages_spoken ?? [],
                    'courses_can_teach' => $teacher->courses_can_teach ?? [],
                    'interests' => $teacher->interests ?? [],
                    'user_id' => $teacher->user_id,
                ],
            ], 200);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Failed to update teacher.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle the suspend status of the resource.
     *
     * @return JsonResponse
     */
    public function suspend($id)
    {
        $teacher = Teacher::with('user')->findOrFail($id);
        $user = $teacher->user;

        DB::beginTransaction();

        try {
            if (! $user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Linked user not found.',
                ], 404);
            }

            $user->suspend_status = $user->suspend_status == 1 ? 0 : 1;
            $user->save();
            $teacher->setRelation('user', $user);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => $teacher->suspend_status
                    ? 'Teacher suspended successfully.'
                    : 'Teacher reactivated successfully.',
                'data' => [
                    'teacher_id' => $teacher->id,
                    'suspend_status' => $teacher->suspend_status,
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
     * List teachers for the public landing page.
     *
     * @return JsonResponse
     */
    public function landTeacher(Request $request)
    {
        $perPage = Pagination::perPage($request);
        $search = $request->search;
        $isTop = $request->is_top;

        $teachers = Teacher::query()
            ->active()
            ->with('user:id,name,email,mobile,image,suspend_status')
            ->when($isTop !== null, function ($query) use ($isTop) {
                $query->where('is_top', (int) $isTop);
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('bio', 'LIKE', "%{$search}%")
                        ->orWhere('expertise', 'LIKE', "%{$search}%")
                        ->orWhere('qualification', 'LIKE', "%{$search}%")
                        ->orWhere('about', 'LIKE', "%{$search}%")
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'LIKE', "%{$search}%"));
                });
            })
            ->select(
                'id',
                'user_id',
                'bio',
                'about',
                'specializations',
                'languages_spoken',
                'courses_can_teach',
                'interests',
                'expertise',
                'qualification',
                'years_of_exp',
                'intro_video',
                'country',
                'timezone',
                'is_top'
            )
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'status' => true,
            'data' => $teachers->items(),

            'pagination' => [
                'current_page' => $teachers->currentPage(),
                'per_page' => $teachers->perPage(),
                'total' => $teachers->total(),
                'last_page' => $teachers->lastPage(),
            ],
        ], 200);
    }

    /**
     * Get a single teacher for the public landing page.
     *
     * @return JsonResponse
     */
    public function show(int $id)
    {
        $teacher = Teacher::query()
            ->where('id', $id)
            ->active()
            ->select(
                'id',
                'user_id',
                'bio',
                'about',
                'specializations',
                'languages_spoken',
                'courses_can_teach',
                'interests',
                'expertise',
                'qualification',
                'years_of_exp',
                'intro_video',
                'country',
                'timezone',
                'is_top'
            )
            ->with([
                'user:id,name,email,mobile,image,suspend_status',
                'batches' => fn ($q) => $q->where('active_status', 1)
                    ->select(
                        'id',
                        'class_id',
                        'teacher_id',
                        'name',
                        'total_seat',
                        'filled_seat',
                        'start_date',
                        'end_date',
                        'status',
                        'active_status'
                    )
                    ->with([
                        'class:id,title,description,short_description,price,duration_in_days,total_classes,image,is_active',
                        'schedules:id,batch_id,day_of_week,start_time,end_time',
                    ])
                    ->latest(),
            ])
            ->first();

        if (! $teacher) {
            return response()->json([
                'status' => false,
                'message' => 'Teacher not found',
            ], 404);
        }

        $dayNames = [
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
        ];

        $batches = $teacher->batches->map(function (Batch $batch) use ($dayNames) {
            return [
                'id' => $batch->id,
                'name' => $batch->name,
                'total_seat' => $batch->total_seat,
                'filled_seat' => $batch->filled_seat,
                'start_date' => optional($batch->start_date)->format('Y-m-d'),
                'end_date' => optional($batch->end_date)->format('Y-m-d'),
                'status' => $batch->status,
                'class' => $batch->class ? [
                    'id' => $batch->class->id,
                    'title' => $batch->class->title,
                    'description' => $batch->class->description,
                    'short_description' => $batch->class->short_description,
                    'price' => $batch->class->price,
                    'duration_in_days' => $batch->class->duration_in_days,
                    'total_classes' => $batch->class->total_classes,
                    'image' => $batch->class->image,
                    'image_url' => $batch->class->image_url,
                ] : null,
                'schedules' => $batch->schedules->map(fn ($schedule) => [
                    'id' => $schedule->id,
                    'day_of_week' => $schedule->day_of_week,
                    'day' => $dayNames[$schedule->day_of_week] ?? 'Unknown',
                    'start_time' => Carbon::parse($schedule->start_time)->format('H:i'),
                    'end_time' => Carbon::parse($schedule->end_time)->format('H:i'),
                ])->values(),
            ];
        })->values();

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'bio' => $teacher->bio,
                'about' => $teacher->about,
                'specializations' => $teacher->specializations ?? [],
                'languages_spoken' => $teacher->languages_spoken ?? [],
                'courses_can_teach' => $teacher->courses_can_teach ?? [],
                'interests' => $teacher->interests ?? [],
                'expertise' => $teacher->expertise,
                'qualification' => $teacher->qualification,
                'years_of_exp' => $teacher->years_of_exp,
                'image' => $teacher->image,
                'image_url' => $teacher->image_url,
                'intro_video' => $teacher->intro_video,
                'intro_video_url' => $teacher->intro_video_url,
                'country' => $teacher->country,
                'timezone' => $teacher->timezone,
                'is_top' => $teacher->is_top,
                'batches' => $batches,
            ],
        ], 200);
    }

    /**
     * Toggle the teacher top status flag.
     *
     * @return JsonResponse
     */
    public function toggleTopStatus($id)
    {
        $teacher = Teacher::findOrFail($id);

        $teacher->is_top = ! $teacher->is_top;

        $teacher->save();

        return response()->json([
            'message' => 'Teacher top status updated successfully',
            'is_top' => $teacher->is_top,
        ]);
    }

    /**
     * Show country and timezone for the authenticated teacher.
     */
    public function showTimezone(): JsonResponse
    {
        $user = auth('api')->user();
        $user->loadMissing('teacher:id,user_id,country,timezone');

        $teacher = $user->teacher;

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Teacher timezone fetched successfully',
            'data' => [
                'country' => $teacher->country,
                'timezone' => $teacher->timezone,
            ],
        ]);
    }

    /**
     * Accept profile array fields as arrays or JSON strings (multipart-friendly).
     */
    private function normalizeProfileArrayInputs(Request $request): void
    {
        foreach (['specializations', 'languages_spoken', 'courses_can_teach', 'interests'] as $field) {
            $value = $request->input($field);

            if (! is_string($value)) {
                continue;
            }

            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $request->merge([$field => $decoded]);
            }
        }
    }
}
