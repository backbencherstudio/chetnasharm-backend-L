<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BatchSchedule;
use App\Models\Setting;
use App\Models\TeacherAvailability;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $user = auth('api')->user();

        if ($user->hasRole('teacher')) {
            $teacherId = $user->teacher->id;
        } else {
            $request->validate([
                'teacher_id' => 'nullable|exists:teachers,id',
            ]);

            $teacherId = $request->teacher_id;
        }

        $query = TeacherAvailability::query();

        if ($teacherId) {
            $query->where('teacher_id', $teacherId);
        }

        if ($request->has('day_of_week')) {
            $query->where('day_of_week', $request->day_of_week);
        }

        $slots = $query->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        $grouped = $slots->groupBy('day_of_week');

        $result = collect(range(0, 6))->map(function ($day) use ($grouped) {

            return [
                'day_of_week' => $day,
                'slots' => isset($grouped[$day])
                    ? $grouped[$day]->map(function ($slot) {
                        return [
                            'id' => $slot->id,
                            'start_time' => Carbon::parse($slot->start_time)->format('H:i'),
                            'end_time' => Carbon::parse($slot->end_time)->format('H:i'),
                        ];
                    })->values()
                    : [],
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Availability fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Store a newly created resource.
     *
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        $user = auth('api')->user();

        if ($user->hasRole('teacher')) {
            $teacherId = $user->teacher->id;
        } else {
            $request->validate([
                'teacher_id' => 'required|exists:teachers,id',
            ]);
            $teacherId = $request->teacher_id;
        }

        $validated = $request->validate([
            'day_of_week' => 'required|integer|min:0|max:6',
            'slots' => 'required|array|min:1',
            'slots.*.start_time' => 'required|date_format:H:i',
        ]);

        $setting = Setting::first();

        if (! $setting || ! $setting->class_time) {
            return response()->json([
                'success' => false,
                'message' => 'Class time not configured',
            ], 422);
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

        return response()->json([
            'success' => true,
            'message' => 'Slots processed successfully',
            'created' => $createdSlots,
            'failed' => $failedSlots,
            'summary' => [
                'total' => count($validated['slots']),
                'created' => count($createdSlots),
                'failed' => count($failedSlots),
            ],
        ]);
    }

    /**
     * Get data for editing the specified resource.
     *
     * @return JsonResponse
     */
    public function edit(Request $request)
    {
        $user = auth('api')->user();

        if ($user->hasRole('teacher')) {
            $teacherId = $user->teacher->id;
        } else {
            $request->validate([
                'teacher_id' => 'required|exists:teachers,id',
            ]);
            $teacherId = $request->teacher_id;
        }

        $validated = $request->validate([
            'day_of_week' => 'required|integer|min:0|max:6',
        ]);

        $slots = TeacherAvailability::where('teacher_id', $teacherId)
            ->where('day_of_week', $validated['day_of_week'])
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Availability slots retrieved successfully',
            'data' => $slots,
        ]);
    }

    /**
     * Update the specified resource.
     *
     * @return JsonResponse
     */
    public function update(Request $request)
    {
        $user = auth('api')->user();

        if ($user->hasRole('teacher')) {
            $teacherId = $user->teacher->id;
        } else {
            $request->validate([
                'teacher_id' => 'required|exists:teachers,id',
            ]);
            $teacherId = $request->teacher_id;
        }

        $validated = $request->validate([
            'day_of_week' => 'required|integer|min:0|max:6',
            'slots' => 'required|array|min:1',
            'slots.*.start_time' => 'required|date_format:H:i',
        ]);

        $setting = Setting::first();

        if (! $setting || ! $setting->class_time) {
            return response()->json([
                'success' => false,
                'message' => 'Class time not configured',
            ], 422);
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

        return response()->json([
            'success' => true,
            'message' => 'Availability synced successfully',
            'created' => $createdSlots,
            'deleted' => $deletedSlots,
            'failed' => $failedSlots,
        ]);
    }

    /**
     * Remove the specified resource.
     *
     * @return JsonResponse
     */
    public function destroy($id)
    {
        $user = auth('api')->user();

        $availability = TeacherAvailability::find($id);

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

        $availability->delete();

        return response()->json([
            'success' => true,
            'message' => 'Availability deleted successfully',
        ]);
    }

    /**
     * Get available teacher slots for a date range.
     *
     * @return JsonResponse
     */
    public function availabilityByDate(Request $request)
    {
        $validated = $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $teacherId = $validated['teacher_id'];
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        $classTime = Setting::first()?->class_time;

        if (! $classTime) {
            return response()->json([
                'success' => false,
                'message' => 'Class time not set in settings',
            ], 422);
        }

        $availabilities = TeacherAvailability::where('teacher_id', $teacherId)->get()->groupBy('day_of_week');

        $schedules = BatchSchedule::with('batch:id,start_date,end_date')
            ->where('teacher_id', $teacherId)
            ->get();

        $result = [];

        while ($startDate->lte($endDate)) {

            $dayOfWeek = $startDate->dayOfWeek;
            $daySlots = [];

            foreach ($availabilities->get($dayOfWeek, collect()) as $availability) {

                $slotStart = Carbon::parse($availability->start_time);
                $slotEnd = Carbon::parse($availability->end_time);

                while ($slotStart->copy()->addMinutes($classTime)->lte($slotEnd)) {

                    $startTime = $slotStart->format('H:i:s');
                    $endTime = $slotStart->copy()->addMinutes($classTime)->format('H:i:s');

                    $conflict = $schedules->contains(function ($schedule) use ($dayOfWeek, $startDate, $startTime, $endTime) {
                        $batch = $schedule->batch;

                        if (
                            ! $batch ||
                            (int) $schedule->day_of_week !== (int) $dayOfWeek ||
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

        return response()->json([
            'success' => true,
            'message' => 'Teacher availability fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Get busy teacher slots for a date range.
     *
     * @return JsonResponse
     */
    public function teacherBusySlots(Request $request)
    {
        $validated = $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $teacherId = $validated['teacher_id'];
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        $schedules = BatchSchedule::with('batch:id,name,start_date,end_date')
            ->where('teacher_id', $teacherId)
            ->get();

        $result = [];

        while ($startDate->lte($endDate)) {

            $dayOfWeek = $startDate->dayOfWeek;

            $dayBusy = [];

            foreach ($schedules as $schedule) {

                $batch = $schedule->batch;

                if (
                    $batch &&
                    $schedule->day_of_week == $dayOfWeek &&
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

        return response()->json([
            'success' => true,
            'message' => 'Teacher busy schedule fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Get busy and available slots for a teacher schedule.
     *
     * @return JsonResponse
     */
    public function teacherSchedule(Request $request)
    {
        $validated = $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $user = auth('api')->user();
        $teacherId = $validated['teacher_id'];

        if ($user->hasRole('teacher') && ! $user->hasRole('admin')) {
            $teacherId = $user->teacher->id ?? 0;

            if ((int) $teacherId !== (int) $validated['teacher_id']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }
        }

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        $classTime = Setting::value('class_time');

        if (! $classTime) {
            return response()->json([
                'success' => false,
                'message' => 'Class time not set in settings',
            ], 422);
        }

        $schedules = BatchSchedule::with('batch:id,name,start_date,end_date')
            ->where('teacher_id', $teacherId)
            ->get();

        $availabilities = TeacherAvailability::where('teacher_id', $teacherId)
            ->get()
            ->groupBy('day_of_week');

        $result = [];

        while ($startDate->lte($endDate)) {

            $currentDate = $startDate->copy();
            $dayOfWeek = $currentDate->dayOfWeek;

            $busySlots = [];
            $availableSlots = [];

            foreach ($schedules as $schedule) {

                $batch = $schedule->batch;

                if (
                    $batch &&
                    $schedule->day_of_week == $dayOfWeek &&
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

        return response()->json([
            'success' => true,
            'message' => 'Teacher schedule fetched successfully',
            'data' => $result,
        ]);
    }
}
