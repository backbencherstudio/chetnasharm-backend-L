<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesBatchAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\GetAttendanceSheetRequest;
use App\Http\Requests\Attendance\GetMonthlyAttendanceRequest;
use App\Http\Requests\Attendance\StoreAttendanceRequest;
use App\Http\Requests\Attendance\UpdateSingleAttendanceRequest;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;

class AttendanceController extends Controller
{
    use AuthorizesBatchAccess;

    public function __construct(private AttendanceService $attendance) {}

    /** Get the attendance sheet for a batch on a given date. */
    public function getAttendanceSheet(GetAttendanceSheetRequest $request, int $batchId): JsonResponse
    {
        $user = auth('api')->user();

        if (! $this->canManageBatch($user, (int) $batchId)) {
            return $this->forbidden('Unauthorized');
        }

        $data = $this->attendance->getAttendanceSheet(
            $batchId,
            $request->query('date'),
            $request->query('search')
        );

        return $this->success($data, 'Attendance sheet fetched');
    }

    /** Save attendance records for a batch class date. */
    public function store(StoreAttendanceRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $validated = $request->validated();

        if (! $this->canManageBatch($user, (int) $validated['batch_id'])) {
            return $this->forbidden('Unauthorized');
        }

        $this->attendance->store($validated);

        return $this->success(message: 'Attendance saved successfully');
    }

    /** Update a single student's attendance for a class date. */
    public function updateSingle(UpdateSingleAttendanceRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $validated = $request->validated();

        if (! $this->canManageBatch($user, (int) $validated['batch_id'])) {
            return $this->forbidden('Unauthorized');
        }

        $result = $this->attendance->updateSingle($validated);

        if (isset($result['error'])) {
            return $this->error($result['error'], 422);
        }

        return $this->success($result['attendance'], 'Attendance updated successfully');
    }

    /** Get monthly attendance markers for a batch. */
    public function getMonthlyAttendance(GetMonthlyAttendanceRequest $request, int $batchId): JsonResponse
    {
        $user = auth('api')->user();

        if (! $this->canManageBatch($user, (int) $batchId)) {
            return $this->forbidden('Unauthorized');
        }

        $data = $this->attendance->getMonthlyAttendance($batchId, $request->query('month'));

        return $this->success($data, 'Monthly attendance fetched');
    }
}
