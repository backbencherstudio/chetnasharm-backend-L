<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreTeacherRequest;
use App\Http\Requests\Teacher\UpdateTeacherRequest;
use App\Http\Resources\PublicTeacherResource;
use App\Http\Resources\TeacherResource;
use App\Models\Teacher;
use App\Services\TeacherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    public function __construct(private readonly TeacherService $teachers) {}

    /** Fetch the paginated teacher list for admin management. */
    public function data(Request $request): JsonResponse
    {
        $teachers = $this->teachers->paginateForAdmin($request);

        return $this->paginate(
            $teachers,
            TeacherResource::collection($teachers->items()),
            'Teacher list fetched successfully'
        );
    }

    /** Create a new teacher with linked user account. */
    public function store(StoreTeacherRequest $request): JsonResponse
    {
        try {
            $validated = $this->teachers->normalizeMobile($request->validated());
        } catch (\Exception $e) {
            return $this->validationError(['mobile' => ['Invalid phone number format.']], 'Invalid phone number format.');
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

            $data = new TeacherResource($teacher)->toArray($request);
            $data['user'] = [
                'id' => $user->id,
                'email' => $user->email,
                'password' => $randomPassword,
            ];

            return $this->created($data, 'Teacher created successfully.');
        } catch (\Throwable $e) {
            return $this->error('Failed to create teacher: '.$e->getMessage(), 500);
        }
    }

    /** Get a teacher for editing. */
    public function edit(int $id): JsonResponse
    {
        $teacher = $this->teachers->findForEdit($id);

        if (! $teacher) {
            return $this->notFound('Teacher not found');
        }

        return $this->success(new TeacherResource($teacher), 'Teacher retrieved successfully.');
    }

    /** Update the specified teacher. */
    public function update(UpdateTeacherRequest $request, int $id): JsonResponse
    {
        $teacher = Teacher::with('user')->findOrFail($id);

        try {
            $validated = $this->teachers->normalizeMobile($request->validated());
        } catch (\Exception $e) {
            return $this->validationError(['mobile' => ['Invalid phone number format.']], 'Invalid phone number format.');
        }

        try {
            $teacher = $this->teachers->update(
                $teacher,
                $validated,
                $request->image('image'),
                $request->file('intro_video'),
            );

            return $this->success(new TeacherResource($teacher), 'Teacher updated successfully.');
        } catch (\Throwable $e) {
            return $this->error('Failed to update teacher: '.$e->getMessage(), 500);
        }
    }

    /** Toggle the suspend status of a teacher. */
    public function suspend(int $id): JsonResponse
    {
        try {
            $result = $this->teachers->toggleSuspend($id);

            if ($result === null) {
                return $this->notFound('Linked user not found.');
            }

            $teacher = $result['teacher'];

            return $this->success([
                'teacher_id' => $teacher->id,
                'suspend_status' => $teacher->suspend_status,
            ], $result['message']);
        } catch (\Throwable $e) {
            return $this->error('Operation failed: '.$e->getMessage(), 500);
        }
    }

    /** List teachers for the public landing page. */
    public function landTeacher(Request $request): JsonResponse
    {
        $teachers = $this->teachers->paginateForLanding($request);

        return $this->paginate(
            $teachers,
            PublicTeacherResource::collection($teachers->items()),
            'Teachers retrieved successfully'
        );
    }

    /** Get a single teacher for the public landing page. */
    public function show(int $id): JsonResponse
    {
        $teacher = $this->teachers->findForPublicShow($id);

        if (! $teacher instanceof Teacher) {
            return $this->notFound('Teacher not found');
        }

        return $this->success(
            new PublicTeacherResource($teacher),
            'Teacher retrieved successfully'
        );
    }

    /** Toggle the teacher top status flag. */
    public function toggleTopStatus(int $id): JsonResponse
    {
        $teacher = $this->teachers->toggleTopStatus($id);

        return $this->success([
            'is_top' => $teacher->is_top,
        ], 'Teacher top status updated successfully');
    }

    /** Show country and timezone for the authenticated teacher. */
    public function showTimezone(): JsonResponse
    {
        $user = auth('api')->user();
        $teacher = $this->teachers->findTimezoneForUser($user);

        if (! $teacher instanceof Teacher) {
            return $this->forbidden('Unauthorized: You are not a teacher');
        }

        return $this->success([
            'country' => $teacher->country,
            'timezone' => $teacher->timezone,
        ], 'Teacher timezone fetched successfully');
    }
}
