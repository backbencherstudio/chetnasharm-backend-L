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
use App\Models\TeacherAvailability;
use App\Services\AvailabilityService;
use Illuminate\Http\JsonResponse;

class AvailabilityController extends Controller
{
    public function __construct(private readonly AvailabilityService $availability) {}

    /** List teacher availability slots grouped by day of week. */
    public function index(IndexAvailabilityRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $validated = $request->validated();
        $teacherId = $this->availability->resolveTeacherId($user, $validated['teacher_id'] ?? null);
        $dayOfWeek = array_key_exists('day_of_week', $validated) ? (int) $validated['day_of_week'] : null;

        $result = $this->availability->index($teacherId, $dayOfWeek);

        return $this->success(
            $result,
            'Availability fetched successfully'
        );
    }

    /** Create availability slots for a teacher on a given day. */
    public function store(StoreAvailabilityRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $validated = $request->validated();
        $teacherId = $this->availability->resolveTeacherId($user, $validated['teacher_id'] ?? null);

        $result = $this->availability->storeSlots($teacherId, $validated);

        if (isset($result['error'])) {
            return $this->error($result['error'], 422);
        }

        return $this->success(
            message: 'Slots processed successfully',
            extra: [
                'created' => $result['created'],
                'failed' => $result['failed'],
                'summary' => $result['summary'],
            ]
        );
    }

    /** Get availability slots for a teacher on a specific day. */
    public function edit(EditAvailabilityRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $validated = $request->validated();
        $teacherId = $this->availability->resolveTeacherId($user, $validated['teacher_id'] ?? null);

        $slots = $this->availability->editSlots($teacherId, $validated['day_of_week']);

        return $this->success(
            $slots,
            'Availability slots retrieved successfully'
        );
    }

    /** Sync availability slots for a teacher on a given day. */
    public function update(UpdateAvailabilityRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $validated = $request->validated();
        $teacherId = $this->availability->resolveTeacherId($user, $validated['teacher_id'] ?? null);

        $result = $this->availability->syncSlots($teacherId, $validated);

        if (isset($result['error'])) {
            return $this->error($result['error'], 422);
        }

        return $this->success(
            message: 'Availability synced successfully',
            extra: [
                'created' => $result['created'],
                'deleted' => $result['deleted'],
                'failed' => $result['failed'],
            ]
        );
    }

    /** Delete a single availability slot. */
    public function destroy(int $id): JsonResponse
    {
        $user = auth('api')->user();

        $availability = $this->availability->find($id);

        if (! $availability instanceof TeacherAvailability) {
            return $this->notFound('Availability not found');
        }

        if ($user->hasRole('teacher') &&
            $availability->teacher_id !== $user->teacher->id) {

            return $this->forbidden('Unauthorized action');
        }

        $this->availability->delete($availability);

        return $this->success(message: 'Availability deleted successfully');
    }

    /** Get available teacher slots for a date range. */
    public function availabilityByDate(AvailabilityByDateRequest $request): JsonResponse
    {
        $result = $this->availability->availabilityByDate($request->validated());

        if (isset($result['error'])) {
            return $this->error($result['error'], 422);
        }

        return $this->success(
            $result,
            'Teacher availability fetched successfully'
        );
    }

    /** Get busy teacher slots for a date range. */
    public function teacherBusySlots(TeacherBusySlotsRequest $request): JsonResponse
    {
        $result = $this->availability->teacherBusySlots($request->validated());

        return $this->success(
            $result,
            'Teacher busy schedule fetched successfully'
        );
    }

    /** Get busy and available slots for a teacher schedule. */
    public function teacherSchedule(TeacherScheduleRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = auth('api')->user();

        if ($user->hasRole('teacher') && ! $user->hasRole('admin')) {
            $teacherId = $user->teacher->id ?? 0;

            if ((int) $teacherId !== (int) $validated['teacher_id']) {
                return $this->forbidden('Unauthorized');
            }
        }

        $result = $this->availability->teacherSchedule($validated);

        if (isset($result['error'])) {
            return $this->error($result['error'], 422);
        }

        return $this->success(
            $result,
            'Teacher schedule fetched successfully'
        );
    }
}
