<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Enrollment;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class AttendanceService
{
    /**
     * @return Collection<int, array{user_id: int, name: string, email: string, status: string}>
     */
    public function getAttendanceSheet(int $batchId, string $date, ?string $search = null): Collection
    {
        $query = Enrollment::query()
            ->where('batch_id', $batchId)
            ->where('status', 'active');

        if ($search) {
            $query->withWhereHas('user', function ($q) use ($search) {
                $q->select('id', 'name', 'email')
                    ->where(function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        } else {
            $query->with('user:id,name,email');
        }

        $enrollments = $query->get();

        $attendanceMap = Attendance::where('batch_id', $batchId)
            ->whereDate('class_date', $date)
            ->pluck('status', 'user_id');

        return $enrollments->map(function ($enrollment) use ($attendanceMap) {
            $user = $enrollment->user;

            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $attendanceMap[$user->id] ?? 'absent',
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function store(array $validated): void
    {
        $enrolledUserIds = Enrollment::where('batch_id', $validated['batch_id'])
            ->whereIn('user_id', collect($validated['attendances'])->pluck('user_id'))
            ->pluck('user_id')
            ->all();

        $enrolledLookup = array_flip($enrolledUserIds);
        $now = now();
        $rows = [];

        foreach ($validated['attendances'] as $item) {
            if (! isset($enrolledLookup[$item['user_id']])) {
                continue;
            }

            $rows[] = [
                'batch_id' => $validated['batch_id'],
                'user_id' => $item['user_id'],
                'class_date' => $validated['class_date'],
                'status' => $item['status'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            Attendance::upsert(
                $rows,
                ['batch_id', 'user_id', 'class_date'],
                ['status', 'updated_at']
            );
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{attendance: Attendance}|array{error: string}
     */
    public function updateSingle(array $validated): array
    {
        $isEnrolled = Enrollment::where('batch_id', $validated['batch_id'])
            ->where('user_id', $validated['user_id'])
            ->exists();

        if (! $isEnrolled) {
            return ['error' => 'Student not enrolled in this batch'];
        }

        $attendance = Attendance::updateOrCreate(
            [
                'batch_id' => $validated['batch_id'],
                'user_id' => $validated['user_id'],
                'class_date' => $validated['class_date'],
            ],
            [
                'status' => $validated['status'],
            ]
        );

        return ['attendance' => $attendance];
    }

    /**
     * @return array<int, array{date: string, has_status: int}>
     */
    public function getMonthlyAttendance(int $batchId, string $month): array
    {
        $start = Carbon::parse($month.'-01')->startOfMonth();
        $end = Carbon::parse($month.'-01')->endOfMonth();

        $attendanceDates = Attendance::where('batch_id', $batchId)
            ->whereBetween('class_date', [$start, $end])
            ->selectRaw('DATE(class_date) as date')
            ->distinct()
            ->pluck('date')
            ->toArray();

        $period = CarbonPeriod::create($start, $end);

        $data = [];

        foreach ($period as $date) {
            $formattedDate = $date->toDateString();

            $data[] = [
                'date' => $formattedDate,
                'has_status' => in_array($formattedDate, $attendanceDates) ? 1 : 0,
            ];
        }

        return $data;
    }
}
