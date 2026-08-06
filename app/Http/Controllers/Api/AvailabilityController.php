<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Availability\AvailabilityByDateRequest;
use App\Http\Requests\Availability\EditAvailabilityRequest;
use App\Http\Requests\Availability\IndexAvailabilityRequest;
use App\Http\Requests\Availability\StoreAvailabilityRequest;
use App\Http\Requests\Availability\TeacherBusySlotsRequest;
use App\Http\Requests\Availability\TeacherScheduleRequest;
use App\Http\Requests\Availability\UpdateAvailabilityRequest;
use App\Services\AvailabilityService;
use Illuminate\Http\JsonResponse;

class AvailabilityController extends Controller
{
    public function __construct(private AvailabilityService $availability) {}

    /** List teacher availability slots grouped by day of week. */
    public function index(IndexAvailabilityRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $validated = $request->validated();
        $teacherId = $this->availability->resolveTeacherId($user, $validated['teacher_id'] ?? null);
        $dayOfWeek = array_key_exists('day_of_week', $validated) ? (int) $validated['day_of_week'] : null;

        $result = $this->availability->index($teacherId, $dayOfWeek);

        return response()->json([
            'success' => true,
            'message' => 'Availability fetched successfully',
            'data' => $result,
        ]);
    }

    /** Create availability slots for a teacher on a given day. */
    public function store(StoreAvailabilityRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $validated = $request->validated();
        $teacherId = $this->availability->resolveTeacherId($user, $validated['teacher_id'] ?? null);

        $result = $this->availability->storeSlots($teacherId, $validated);

        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Slots processed successfully',
            'created' => $result['created'],
            'failed' => $result['failed'],
            'summary' => $result['summary'],
        ]);
    }

    /** Get availability slots for a teacher on a specific day. */
    public function edit(EditAvailabilityRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $validated = $request->validated();
        $teacherId = $this->availability->resolveTeacherId($user, $validated['teacher_id'] ?? null);

        $slots = $this->availability->editSlots($teacherId, $validated['day_of_week']);

        return response()->json([
            'success' => true,
            'message' => 'Availability slots retrieved successfully',
            'data' => $slots,
        ]);
    }

    /** Sync availability slots for a teacher on a given day. */
    public function update(UpdateAvailabilityRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $validated = $request->validated();
        $teacherId = $this->availability->resolveTeacherId($user, $validated['teacher_id'] ?? null);

        $result = $this->availability->syncSlots($teacherId, $validated);

        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Availability synced successfully',
            'created' => $result['created'],
            'deleted' => $result['deleted'],
            'failed' => $result['failed'],
        ]);
    }

    /** Delete a single availability slot. */
    public function destroy(int $id): JsonResponse
    {
        $user = auth('api')->user();

        $availability = $this->availability->find($id);

        if (! $availability) {
            return response()->json([
                'success' => false,
                'message' => 'Availability not found',
            ], 404);
        }

        if ($user->hasRole('teacher') &&
            $availability->teacher_id !== $user->teacher->id) {

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action',
            ], 403);
        }

        $this->availability->delete($availability);

        return response()->json([
            'success' => true,
            'message' => 'Availability deleted successfully',
        ]);
    }

    /** Get available teacher slots for a date range. */
    public function availabilityByDate(AvailabilityByDateRequest $request): JsonResponse
    {
        $result = $this->availability->availabilityByDate($request->validated());

        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Teacher availability fetched successfully',
            'data' => $result,
        ]);
    }

    /** Get busy teacher slots for a date range. */
    public function teacherBusySlots(TeacherBusySlotsRequest $request): JsonResponse
    {
        $result = $this->availability->teacherBusySlots($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Teacher busy schedule fetched successfully',
            'data' => $result,
        ]);
    }

    /** Get busy and available slots for a teacher schedule. */
    public function teacherSchedule(TeacherScheduleRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = auth('api')->user();

        if ($user->hasRole('teacher') && ! $user->hasRole('admin')) {
            $teacherId = $user->teacher->id ?? 0;

            if ((int) $teacherId !== (int) $validated['teacher_id']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }
        }

        $result = $this->availability->teacherSchedule($validated);

        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Teacher schedule fetched successfully',
            'data' => $result,
        ]);
    }
}
