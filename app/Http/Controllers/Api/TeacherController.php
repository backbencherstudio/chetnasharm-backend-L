<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreTeacherRequest;
use App\Http\Requests\Teacher\UpdateTeacherRequest;
use App\Models\Teacher;
use App\Services\TeacherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    public function __construct(private TeacherService $teachers) {}

    /** Fetch the paginated teacher list for admin management. */
    public function data(Request $request): JsonResponse
    {
        $teachers = $this->teachers->paginateForAdmin($request);

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

    /** Create a new teacher with linked user account. */
    public function store(StoreTeacherRequest $request): JsonResponse
    {
        try {
            $validated = $this->teachers->normalizeMobile($request->validated());
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid phone number format.',
            ], 422);
        }

        try {
            $result = $this->teachers->create(
                $validated,
                $request->image('image'),
                $request->file('intro_video'),
            );

            $teacher = $result['teacher'];
            $user = $result['user'];
            $randomPassword = $result['password'];

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
            return response()->json([
                'status' => false,
                'message' => 'Failed to create teacher.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /** Get a teacher for editing. */
    public function edit(int $id): JsonResponse
    {
        $teacher = $this->teachers->findForEdit($id);

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

    /** Update the specified teacher. */
    public function update(UpdateTeacherRequest $request, int $id): JsonResponse
    {
        $teacher = Teacher::with('user')->findOrFail($id);

        try {
            $validated = $this->teachers->normalizeMobile($request->validated());
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid phone number format.',
            ], 422);
        }

        try {
            $teacher = $this->teachers->update(
                $teacher,
                $validated,
                $request->image('image'),
                $request->file('intro_video'),
            );

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
            return response()->json([
                'status' => false,
                'message' => 'Failed to update teacher.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /** Toggle the suspend status of a teacher. */
    public function suspend(int $id): JsonResponse
    {
        try {
            $result = $this->teachers->toggleSuspend($id);

            if ($result === null) {
                return response()->json([
                    'status' => false,
                    'message' => 'Linked user not found.',
                ], 404);
            }

            $teacher = $result['teacher'];

            return response()->json([
                'status' => true,
                'message' => $result['message'],
                'data' => [
                    'teacher_id' => $teacher->id,
                    'suspend_status' => $teacher->suspend_status,
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

    /** List teachers for the public landing page. */
    public function landTeacher(Request $request): JsonResponse
    {
        $teachers = $this->teachers->paginateForLanding($request);

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

    /** Get a single teacher for the public landing page. */
    public function show(int $id): JsonResponse
    {
        $teacher = $this->teachers->findForPublicShow($id);

        if (! $teacher) {
            return response()->json([
                'status' => false,
                'message' => 'Teacher not found',
            ], 404);
        }

        $batches = $this->teachers->formatPublicBatches($teacher);

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

    /** Toggle the teacher top status flag. */
    public function toggleTopStatus(int $id): JsonResponse
    {
        $teacher = $this->teachers->toggleTopStatus($id);

        return response()->json([
            'message' => 'Teacher top status updated successfully',
            'is_top' => $teacher->is_top,
        ]);
    }

    /** Show country and timezone for the authenticated teacher. */
    public function showTimezone(): JsonResponse
    {
        $user = auth('api')->user();
        $teacher = $this->teachers->findTimezoneForUser($user);

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
}
