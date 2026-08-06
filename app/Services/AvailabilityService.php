<?php

namespace App\Services;

use App\Models\BatchSchedule;
use App\Models\Setting;
use App\Models\TeacherAvailability;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AvailabilityService
{
    /**
     * @return array<int, array{day_of_week: int, slots: array<int, array{id: int, start_time: string, end_time: string}>}>
     */
    public function index(?int $teacherId, ?int $dayOfWeek = null): array
    {
        $query = TeacherAvailability::query();

        if ($teacherId) {
            $query->where('teacher_id', $teacherId);
        }

        if ($dayOfWeek !== null) {
            $query->where('day_of_week', $dayOfWeek);
        }

        $slots = $query->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        $grouped = $slots->groupBy('day_of_week');

        return collect(range(0, 6))->map(function ($day) use ($grouped) {
            return [
                'day_of_week' => $day,
                'slots' => isset($grouped[$day])
                    ? $grouped[$day]->map(function ($slot) {
                        return [
                            'id' => $slot->id,
                            'start_time' => Carbon::parse($slot->start_time)->format('H:i'),
                            'end_time' => Carbon::parse($slot->end_time)->format('H:i'),
                        ];
                    })->values()->all()
                    : [],
            ];
        })->values()->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{created: array<int, TeacherAvailability>, failed: array<int, array{start_time: string, message: string}>, summary: array{total: int, created: int, failed: int}}|array{error: string}
     */
    public function storeSlots(int $teacherId, array $validated): array
    {
        $setting = Setting::first();

        if (! $setting || ! $setting->class_time) {
            return ['error' => 'Class time not configured'];
        }

        $classTime = (int) $setting->class_time;

        $createdSlots = [];
        $failedSlots = [];

        foreach ($validated['slots'] as $slot) {
            $startTime = Carbon::createFromFormat('H:i', $slot['start_time']);
            $endTime = $startTime->copy()->addMinutes($classTime);

            if ($endTime->gt(Carbon::createFromTime(23, 59))) {
                $failedSlots[] = [
                    'start_time' => $startTime->format('H:i'),
                    'message' => 'Exceeds day limit',
                ];

                continue;
            }

            $overlap = TeacherAvailability::where('teacher_id', $teacherId)
                ->where('day_of_week', $validated['day_of_week'])
                ->where(function ($q) use ($startTime, $endTime) {
                    $q->where('start_time', '<', $endTime->format('H:i:s'))
                        ->where('end_time', '>', $startTime->format('H:i:s'));
                })
                ->exists();

            if ($overlap) {
                $failedSlots[] = [
                    'start_time' => $startTime->format('H:i'),
                    'message' => 'Overlaps with existing slot',
                ];

                continue;
            }

            $createdSlots[] = TeacherAvailability::create([
                'teacher_id' => $teacherId,
                'day_of_week' => $validated['day_of_week'],
                'start_time' => $startTime->format('H:i:s'),
                'end_time' => $endTime->format('H:i:s'),
                'booked_status' => 0,
                'booked_until' => null,
            ]);
        }

        return [
            'created' => $createdSlots,
            'failed' => $failedSlots,
            'summary' => [
                'total' => count($validated['slots']),
                'created' => count($createdSlots),
                'failed' => count($failedSlots),
            ],
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, TeacherAvailability>
     */
    public function editSlots(int $teacherId, int $dayOfWeek): Collection
    {
        return TeacherAvailability::where('teacher_id', $teacherId)
            ->where('day_of_week', $dayOfWeek)
            ->orderBy('start_time')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{created: array<int, TeacherAvailability>, deleted: array<int, string>, failed: array<int, array{start_time: string, message: string}>}|array{error: string}
     */
    public function syncSlots(int $teacherId, array $validated): array
    {
        $setting = Setting::first();

        if (! $setting || ! $setting->class_time) {
            return ['error' => 'Class time not configured'];
        }

        $classTime = (int) $setting->class_time;

        $existing = TeacherAvailability::where('teacher_id', $teacherId)
            ->where('day_of_week', $validated['day_of_week'])
            ->get();

        $existingMap = $existing->mapWithKeys(function ($slot) {
            $key = Carbon::parse($slot->start_time)->format('H:i');

            return [$key => $slot];
        });

        $newSlots = collect($validated['slots'])->mapWithKeys(function ($slot) use ($classTime) {
            $start = Carbon::createFromFormat('H:i', $slot['start_time']);
            $end = $start->copy()->addMinutes($classTime);

            return [
                $start->format('H:i') => [
                    'start_time' => $start,
                    'end_time' => $end,
                ],
            ];
        });

        $createdSlots = [];
        $deletedSlots = [];
        $failedSlots = [];

        foreach ($existingMap as $start => $slot) {
            if (! $newSlots->has($start)) {
                $slot->delete();
                $deletedSlots[] = $start;
            }
        }

        foreach ($newSlots as $start => $slotData) {
            if ($existingMap->has($start)) {
                continue;
            }

            $startTime = $slotData['start_time'];
            $endTime = $slotData['end_time'];

            if ($endTime->gt(Carbon::createFromTime(23, 59))) {
                $failedSlots[] = [
                    'start_time' => $start,
                    'message' => 'Exceeds day limit',
                ];

                continue;
            }

            $overlap = TeacherAvailability::where('teacher_id', $teacherId)
                ->where('day_of_week', $validated['day_of_week'])
                ->where(function ($q) use ($startTime, $endTime) {
                    $q->where('start_time', '<', $endTime->format('H:i:s'))
                        ->where('end_time', '>', $startTime->format('H:i:s'));
                })
                ->exists();

            if ($overlap) {
                $failedSlots[] = [
                    'start_time' => $start,
                    'message' => 'Overlaps with existing slot',
                ];

                continue;
            }

            $createdSlots[] = TeacherAvailability::create([
                'teacher_id' => $teacherId,
                'day_of_week' => $validated['day_of_week'],
                'start_time' => $startTime->format('H:i:s'),
                'end_time' => $endTime->format('H:i:s'),
            ]);
        }

        return [
            'created' => $createdSlots,
            'deleted' => $deletedSlots,
            'failed' => $failedSlots,
        ];
    }

    public function find(int $id): ?TeacherAvailability
    {
        return TeacherAvailability::find($id);
    }

    public function delete(TeacherAvailability $availability): void
    {
        $availability->delete();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, array{date: string, day: int, slots: array<int, array{start_time: string, end_time: string}>}>|array{error: string}
     */
    public function availabilityByDate(array $validated): array
    {
        $teacherId = $validated['teacher_id'];
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        $classTime = Setting::first()?->class_time;

        if (! $classTime) {
            return ['error' => 'Class time not set in settings'];
        }

        $availabilities = TeacherAvailability::where('teacher_id', $teacherId)->get()->groupBy('day_of_week');

        $schedules = $this->teacherSchedulesInRange(
            $teacherId,
            $startDate->copy(),
            $endDate->copy(),
            ['id', 'start_date', 'end_date']
        )->groupBy('day_of_week');

        $result = [];

        while ($startDate->lte($endDate)) {
            $dayOfWeek = $startDate->dayOfWeek;
            $daySlots = [];
            $daySchedules = $schedules->get($dayOfWeek, collect());

            foreach ($availabilities->get($dayOfWeek, collect()) as $availability) {
                $slotStart = Carbon::parse($availability->start_time);
                $slotEnd = Carbon::parse($availability->end_time);

                while ($slotStart->copy()->addMinutes($classTime)->lte($slotEnd)) {
                    $startTime = $slotStart->format('H:i:s');
                    $endTime = $slotStart->copy()->addMinutes($classTime)->format('H:i:s');

                    $conflict = $daySchedules->contains(function ($schedule) use ($startDate, $startTime, $endTime) {
                        $batch = $schedule->batch;

                        if (
                            ! $batch ||
                            ! $startDate->between(
                                Carbon::parse($batch->start_date)->startOfDay(),
                                Carbon::parse($batch->end_date)->endOfDay()
                            )
                        ) {
                            return false;
                        }

                        return ($startTime >= $schedule->start_time && $startTime < $schedule->end_time)
                            || ($endTime > $schedule->start_time && $endTime <= $schedule->end_time)
                            || ($startTime <= $schedule->start_time && $endTime >= $schedule->end_time);
                    });

                    if (! $conflict) {
                        $daySlots[] = [
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                        ];
                    }

                    $slotStart->addMinutes($classTime);
                }
            }

            $result[] = [
                'date' => $startDate->toDateString(),
                'day' => $dayOfWeek,
                'slots' => $daySlots,
            ];

            $startDate->addDay();
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, array{date: string, day: int, busy_slots: array<int, array{start_time: mixed, end_time: mixed, batch_id: int, batch_name: string}>}>
     */
    public function teacherBusySlots(array $validated): array
    {
        $teacherId = $validated['teacher_id'];
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        $schedules = $this->teacherSchedulesInRange(
            $teacherId,
            $startDate->copy(),
            $endDate->copy(),
            ['id', 'name', 'start_date', 'end_date']
        )->groupBy('day_of_week');

        $result = [];

        while ($startDate->lte($endDate)) {
            $dayOfWeek = $startDate->dayOfWeek;

            $dayBusy = [];

            foreach ($schedules->get($dayOfWeek, collect()) as $schedule) {
                $batch = $schedule->batch;

                if (
                    $batch &&
                    $startDate->between(
                        Carbon::parse($batch->start_date),
                        Carbon::parse($batch->end_date)
                    )
                ) {
                    $dayBusy[] = [
                        'start_time' => $schedule->start_time,
                        'end_time' => $schedule->end_time,
                        'batch_id' => $batch->id,
                        'batch_name' => $batch->name,
                    ];
                }
            }

            $result[] = [
                'date' => $startDate->toDateString(),
                'day' => $dayOfWeek,
                'busy_slots' => $dayBusy,
            ];

            $startDate->addDay();
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, array{date: string, day: int, busy_slots: array<int, array{start_time: mixed, end_time: mixed, batch_id: int, batch_name: string}>, available_slots: array<int, array{start_time: string, end_time: string}>}>|array{error: string}
     */
    public function teacherSchedule(array $validated): array
    {
        $teacherId = $validated['teacher_id'];
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        $classTime = Setting::value('class_time');

        if (! $classTime) {
            return ['error' => 'Class time not set in settings'];
        }

        $schedules = $this->teacherSchedulesInRange(
            $teacherId,
            $startDate->copy(),
            $endDate->copy(),
            ['id', 'name', 'start_date', 'end_date']
        )->groupBy('day_of_week');

        $availabilities = TeacherAvailability::where('teacher_id', $teacherId)
            ->get()
            ->groupBy('day_of_week');

        $result = [];

        while ($startDate->lte($endDate)) {
            $currentDate = $startDate->copy();
            $dayOfWeek = $currentDate->dayOfWeek;

            $busySlots = [];
            $availableSlots = [];

            foreach ($schedules->get($dayOfWeek, collect()) as $schedule) {
                $batch = $schedule->batch;

                if (
                    $batch &&
                    $currentDate->between(
                        Carbon::parse($batch->start_date),
                        Carbon::parse($batch->end_date)
                    )
                ) {
                    $busySlots[] = [
                        'start_time' => $schedule->start_time,
                        'end_time' => $schedule->end_time,
                        'batch_id' => $batch->id,
                        'batch_name' => $batch->name,
                    ];
                }
            }

            $dayAvailabilities = $availabilities[$dayOfWeek] ?? collect();

            foreach ($dayAvailabilities as $availability) {
                $slotStart = Carbon::parse($availability->start_time);
                $slotEnd = Carbon::parse($availability->end_time);

                while ($slotStart->copy()->addMinutes($classTime)->lte($slotEnd)) {
                    $startTime = $slotStart->format('H:i:s');
                    $endTime = $slotStart->copy()->addMinutes($classTime)->format('H:i:s');

                    $isBusy = collect($busySlots)->contains(function ($busy) use ($startTime, $endTime) {
                        return
                            $busy['start_time'] < $endTime &&
                            $busy['end_time'] > $startTime;
                    });

                    if (! $isBusy) {
                        $availableSlots[] = [
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                        ];
                    }

                    $slotStart->addMinutes($classTime);
                }
            }

            $result[] = [
                'date' => $currentDate->toDateString(),
                'day' => $dayOfWeek,
                'busy_slots' => $busySlots,
                'available_slots' => $availableSlots,
            ];

            $startDate->addDay();
        }

        return $result;
    }

    public function resolveTeacherId(User $user, ?int $requestTeacherId): ?int
    {
        if ($user->hasRole('teacher')) {
            return $user->teacher->id;
        }

        return $requestTeacherId;
    }

    /** Load teacher schedules whose batches overlap the given date range. */
    private function teacherSchedulesInRange(
        int $teacherId,
        Carbon $startDate,
        Carbon $endDate,
        array $batchColumns = ['id', 'start_date', 'end_date']
    ): Collection {
        return BatchSchedule::with(['batch' => fn ($query) => $query->select($batchColumns)])
            ->where('teacher_id', $teacherId)
            ->whereHas('batch', function ($query) use ($startDate, $endDate) {
                $query->whereDate('start_date', '<=', $endDate->toDateString())
                    ->whereDate('end_date', '>=', $startDate->toDateString());
            })
            ->get();
    }
}
